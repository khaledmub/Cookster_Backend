<?php

namespace App\Console\Commands;

use App\Services\S3Service;
use App\Services\VideoMediaService;
use App\Services\VideoMp4FastStart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class VideosMediaStatusCommand extends Command
{
    protected $signature = 'videos:media-status
                            {--scan : Slow: scan full ready catalog for ladder/fast-start gaps (S3 checks)}';

    protected $description = 'Show video-processing queue depth, transcode counts, and backfill gap summary';

    public function handle(S3Service $s3, VideoMp4FastStart $fastStart): int
    {
        $queue = (int) Redis::llen('queues:video-processing');
        $reserved = (int) Redis::zcard('queues:video-processing:reserved');
        $delayed = (int) Redis::zcard('queues:video-processing:delayed');
        $failedJobs = (int) DB::table('failed_jobs')->count();

        $this->info('Video media backfill status');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Queue (video-processing) waiting', (string) $queue],
                ['Queue reserved (in flight)', (string) $reserved],
                ['Queue delayed', (string) $delayed],
                ['failed_jobs (all queues)', (string) $failedJobs],
            ]
        );

        $this->renderInFlightJobs();

        if (! Schema::hasColumn('videos', 'transcode_status')) {
            $this->warn('transcode_status column missing');

            return self::SUCCESS;
        }

        $statusCounts = DB::table('videos')
            ->where('status', 1)
            ->where('is_soft_delete', 0)
            ->select('transcode_status', DB::raw('count(*) as c'))
            ->groupBy('transcode_status')
            ->pluck('c', 'transcode_status');

        $ready = (int) ($statusCounts['ready'] ?? 0);
        $pending = (int) ($statusCounts['pending'] ?? 0);
        $failed = (int) ($statusCounts['failed'] ?? 0);

        $this->newLine();
        $this->table(
            ['Published video transcode_status', 'Count'],
            [
                ['ready', (string) $ready],
                ['pending', (string) $pending],
                ['failed', (string) $failed],
            ]
        );

        if ($this->option('scan')) {
            $this->scanCatalogGaps($s3, $fastStart);
        } else {
            $this->newLine();
            $this->line('Run with <comment>--scan</comment> for full-catalog gap counts (S3 checks, ~2–3 min).');
        }

        $this->newLine();
        $this->line('Useful commands:');
        $this->line('  php artisan videos:media-status');
        $this->line('  php artisan videos:media-status --scan');
        $this->line('  php artisan videos:backfill-media --reencode-faststart --force --limit=500');
        $this->line('  php artisan videos:backfill-media --upgrade-ladder --limit=500');
        $this->line('  php artisan videos:validate-media --sample=10 --api-check');
        $this->line('  redis-cli LLEN '.config('database.redis.options.prefix').'queues:video-processing');

        return self::SUCCESS;
    }

    private function scanCatalogGaps(S3Service $s3, VideoMp4FastStart $fastStart): void
    {
        $this->newLine();
        $this->info('Scanning ready catalog on object storage…');

        $needs720 = 0;
        $needs1080 = 0;
        $needsBlur = 0;
        $needs360 = 0;
        $needsFastStart = 0;
        $scanned = 0;

        $query = DB::table('videos')
            ->where('status', 1)
            ->where('is_soft_delete', 0)
            ->where('transcode_status', 'ready')
            ->whereNotNull('video')
            ->where('video', '!=', '')
            ->orderByDesc('system_id');

        foreach ($query->cursor() as $video) {
            $scanned++;
            $id = (string) $video->id;

            if (! $s3->fileExists(VideoMediaService::mp4Key($id, 360))) {
                $needs360++;
            }

            $hlsHas720 = $s3->fileExists('videos/'.$id.'/hls/video_720.m3u8');
            if ($hlsHas720 && ! $s3->fileExists(VideoMediaService::mp4Key($id, 720))) {
                $needs720++;
            }

            $hlsHas1080 = $s3->fileExists('videos/'.$id.'/hls/video_1080.m3u8');
            if ($hlsHas1080 && ! $s3->fileExists(VideoMediaService::mp4Key($id, 1080))) {
                $needs1080++;
            }

            if (! $s3->fileExists(VideoMediaService::posterBlurKey($id))) {
                $needsBlur++;
            }

            foreach ([360, 720, 1080] as $height) {
                $key = VideoMediaService::mp4Key($id, $height);
                if (! $s3->fileExists($key)) {
                    continue;
                }

                $appears = $fastStart->appearsFastStartOnStorage($key);
                if ($appears === false || $appears === null) {
                    $needsFastStart++;
                    break;
                }
            }
        }

        $this->table(
            ['Gap (ready catalog)', 'Count'],
            [
                ['Scanned', (string) $scanned],
                ['Missing 360.mp4', (string) $needs360],
                ['Missing 720.mp4 (HLS has 720)', (string) $needs720],
                ['Missing 1080.mp4 (HLS has 1080)', (string) $needs1080],
                ['Missing thumb_blur.webp', (string) $needsBlur],
                ['Likely needs fast-start remux', (string) $needsFastStart],
            ]
        );
    }

    private function renderInFlightJobs(): void
    {
        $raw = Redis::zrange('queues:video-processing:reserved', 0, -1);
        if ($raw === [] || $raw === false) {
            return;
        }

        $rows = [];
        foreach ($raw as $payload) {
            $job = json_decode($payload, true);
            if (! is_array($job)) {
                continue;
            }

            $name = (string) ($job['displayName'] ?? 'unknown');
            $shortName = class_basename($name);
            $videoId = '?';

            if (isset($job['data']['command']) && is_string($job['data']['command'])) {
                if (preg_match('/videoId";s:36:"([a-f0-9-]{36})"/', $job['data']['command'], $m)) {
                    $videoId = $m[1];
                }
            }

            $rows[] = [$shortName, substr($videoId, 0, 8).'…', (string) ($job['attempts'] ?? 1)];
        }

        if ($rows === []) {
            return;
        }

        $this->newLine();
        $this->info('In-flight jobs (heavy ProcessVideoJob = full transcode, can take 30–60+ min each)');
        $this->table(['Job', 'Video ID', 'Attempt'], $rows);
    }
}
