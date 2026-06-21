<?php

namespace App\Console\Commands;

use App\Helpers\AppHelper;
use App\Services\S3Service;
use App\Services\VideoMediaService;
use App\Services\VideoMp4FastStart;
use App\Support\CdnUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class VideosValidateMediaCommand extends Command
{
    protected $signature = 'videos:validate-media
                            {--video-id=* : Specific video UUID(s) to validate}
                            {--sample=10 : Random sample size when no IDs given}
                            {--limit=500 : Max catalog videos to scan}
                            {--api-check : Validate decorateVideoRow API contract}
                            {--json : Output JSON report}';

    protected $description = 'Validate CDN Range/fast-start headers and optional API payload for ready videos';

    public function handle(S3Service $s3, VideoMp4FastStart $fastStart): int
    {
        $ids = $this->resolveVideoIds();
        if ($ids === []) {
            $this->error('No ready videos found to validate');

            return self::FAILURE;
        }

        $results = [];
        $passed = 0;

        foreach ($ids as $id) {
            $result = $this->validateVideo($id, $s3, $fastStart);
            $results[] = $result;
            if ($result['passed']) {
                $passed++;
            }

            if (! $this->option('json')) {
                $this->renderLine($result);
            }
        }

        $total = count($results);
        $pct = $total > 0 ? round(($passed / $total) * 100, 1) : 0.0;

        if ($this->option('json')) {
            $this->line(json_encode([
                'total' => $total,
                'passed' => $passed,
                'pass_rate_pct' => $pct,
                'acceptance_threshold_pct' => 95,
                'acceptance_met' => $pct >= 95,
                'results' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->newLine();
            $this->info(sprintf(
                'Summary: %d/%d passed (%.1f%%) — acceptance target ≥95%% on 360.mp4 fast-start + Range 206',
                $passed,
                $total,
                $pct
            ));
        }

        return $pct >= 95 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function resolveVideoIds(): array
    {
        $explicit = array_values(array_filter(array_map('strval', $this->option('video-id') ?? [])));
        if ($explicit !== []) {
            return $explicit;
        }

        if (! Schema::hasColumn('videos', 'transcode_status')) {
            return [];
        }

        $limit = max(1, (int) $this->option('limit'));
        $sample = max(1, (int) $this->option('sample'));
        $take = min($limit, $sample);

        return DB::table('videos')
            ->where('status', 1)
            ->where('is_soft_delete', 0)
            ->where('transcode_status', 'ready')
            ->whereNotNull('video')
            ->where('video', '!=', '')
            ->inRandomOrder()
            ->limit($take)
            ->pluck('id')
            ->map(static fn ($id) => (string) $id)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateVideo(string $id, S3Service $s3, VideoMp4FastStart $fastStart): array
    {
        $checks = [];
        $key360 = VideoMediaService::mp4Key($id, 360);
        $url360 = CdnUrl::forPath($key360);

        if (! $s3->fileExists($key360)) {
            return [
                'video_id' => $id,
                'passed' => false,
                'checks' => ['360.mp4' => 'missing on storage'],
            ];
        }

        $checks = array_merge($checks, $this->checkCdnHeaders($url360));
        $checks = array_merge($checks, $this->checkRange206($url360, '360'));
        $checks = array_merge($checks, $this->checkFastStart($id, 360, $s3, $fastStart));
        $checks = array_merge($checks, $this->checkFfprobe($url360, '360'));

        if ($s3->fileExists('videos/'.$id.'/hls/video_1080.m3u8')) {
            $key1080 = VideoMediaService::mp4Key($id, 1080);
            if ($s3->fileExists($key1080)) {
                $url1080 = CdnUrl::forPath($key1080);
                $checks = array_merge($checks, $this->checkRange206($url1080, '1080'));
                $checks = array_merge($checks, $this->checkFastStart($id, 1080, $s3, $fastStart));
            } else {
                $checks['1080.mp4'] = 'missing on storage';
            }
        }

        if ($this->option('api-check')) {
            $checks = array_merge($checks, $this->checkApiPayload($id, $s3));
        }

        $blurKey = VideoMediaService::posterBlurKey($id);
        $checks['thumbnail_blur_storage'] = $s3->fileExists($blurKey) ? 'ok' : 'missing';

        $failed = array_filter($checks, static fn ($v) => $v !== 'ok');

        return [
            'video_id' => $id,
            'passed' => $failed === [],
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function checkCdnHeaders(string $url): array
    {
        try {
            $response = Http::timeout(15)->head($url);
        } catch (\Throwable $e) {
            return ['cdn_head' => 'error: '.$e->getMessage()];
        }

        $checks = [];
        $checks['cdn_status'] = $response->status() === 200 ? 'ok' : 'http_'.$response->status();

        $acceptRanges = strtolower((string) $response->header('Accept-Ranges', ''));
        $checks['accept_ranges'] = str_contains($acceptRanges, 'bytes') ? 'ok' : 'missing';

        $cacheControl = strtolower((string) $response->header('Cache-Control', ''));
        $checks['cache_control'] = str_contains($cacheControl, 'max-age') ? 'ok' : 'missing';

        return $checks;
    }

    /**
     * @return array<string, string>
     */
    private function checkRange206(string $url, string $label = '360'): array
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['Range' => 'bytes=0-262143'])
                ->get($url);
        } catch (\Throwable $e) {
            return ['range_206_'.$label => 'error: '.$e->getMessage()];
        }

        return [
            'range_206_'.$label => $response->status() === 206 ? 'ok' : 'http_'.$response->status(),
            'range_body_'.$label => strlen($response->body()) > 0 ? 'ok' : 'empty',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function checkFastStart(string $id, int $height, S3Service $s3, VideoMp4FastStart $fastStart): array
    {
        $key = VideoMediaService::mp4Key($id, $height);
        $appears = $fastStart->appearsFastStartOnStorage($key);

        if ($appears === true) {
            return ['fast_start_'.$height => 'ok'];
        }

        if ($appears === false) {
            return ['fast_start_'.$height => 'moov_after_mdat'];
        }

        $tmp = storage_path('app/validate-media/'.$id.'_'.$height.'.mp4');
        $dir = dirname($tmp);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            file_put_contents($tmp, $s3->retrieveFile($key));

            return [
                'fast_start_'.$height => $fastStart->isFastStart($tmp) ? 'ok' : 'moov_after_mdat',
            ];
        } catch (\Throwable $e) {
            return ['fast_start_'.$height => 'error: '.$e->getMessage()];
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function checkFfprobe(string $url, string $label = '360'): array
    {
        $ffprobe = (string) config('ffmpeg.ffprobe.binaries', '/usr/bin/ffprobe');
        $tmp = storage_path('app/validate-media/probe_'.md5($url).'.mp4');
        $dir = dirname($tmp);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Range' => 'bytes=0-524287'])
                ->get($url);

            if ($response->status() !== 206 && $response->status() !== 200) {
                return ['ffprobe_'.$label => 'download_http_'.$response->status()];
            }

            file_put_contents($tmp, $response->body());

            $process = new Process([
                $ffprobe,
                '-v', 'error',
                '-show_entries', 'format=format_name',
                '-of', 'default=nw=1',
                $tmp,
            ]);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                return ['ffprobe_'.$label => 'failed'];
            }

            $output = trim($process->getOutput());

            return ['ffprobe_'.$label => str_contains($output, 'mp4') || str_contains($output, 'mov') ? 'ok' : 'unexpected:'.$output];
        } catch (\Throwable $e) {
            return ['ffprobe_'.$label => 'error: '.$e->getMessage()];
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function checkApiPayload(string $id, S3Service $s3): array
    {
        $video = DB::table('videos')->where('id', $id)->first();
        if (! $video) {
            return ['api_row' => 'missing'];
        }

        AppHelper::decorateVideoRow($video);

        $checks = [];
        $checks['api_transcode_status'] = ($video->transcode_status ?? '') === 'ready' ? 'ok' : 'not_ready';
        $isImage = (int) ($video->is_image ?? 0) === 1;

        if ($isImage) {
            $checks['api_photo_video_url'] = VideoMediaService::isStaticImageUrl($video->video_url ?? null) ? 'ok' : 'not_image';
            $checks['api_photo_sources_null'] = empty($video->video_sources) ? 'ok' : 'unexpected_sources';

            return $checks;
        }

        if (($video->transcode_status ?? '') === 'ready') {
            $checks['api_video_url_not_image'] = VideoMediaService::isStaticImageUrl($video->video_url ?? null)
                ? 'jpg_in_video_url'
                : 'ok';
            $checks['api_video_url_is_mp4'] = str_contains((string) ($video->video_url ?? ''), '.mp4') ? 'ok' : 'not_mp4';
        }

        $checks['api_thumbnail_blur'] = ! empty($video->thumbnail_blur) ? 'ok' : 'null';
        $checks['api_url_360'] = ! empty($video->video_sources['url_360'] ?? null) ? 'ok' : 'null';

        foreach ([720, 1080] as $height) {
            $key = "url_{$height}";
            $url = $video->video_sources[$key] ?? null;
            $exists = $s3->fileExists(VideoMediaService::mp4Key($id, $height));

            if ($exists && empty($url)) {
                $checks['api_'.$key] = 'missing_url_but_file_exists';
            } elseif (! $exists && ! empty($url)) {
                $checks['api_'.$key] = 'phantom_url';
            } else {
                $checks['api_'.$key] = 'ok';
            }
        }

        return $checks;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function renderLine(array $result): void
    {
        $status = $result['passed'] ? '<info>PASS</info>' : '<error>FAIL</error>';
        $failedChecks = array_filter($result['checks'], static fn ($v) => $v !== 'ok');
        $detail = $failedChecks === [] ? '' : ' — '.json_encode($failedChecks);

        $this->line(sprintf('%s %s%s', $status, $result['video_id'], $detail));
    }
}
