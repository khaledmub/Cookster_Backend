<?php

namespace App\Console\Commands;

use App\Jobs\ProcessVideoJob;
use App\Jobs\ProcessVideoThumbnailJob;
use App\Jobs\ReencodeFastStartJob;
use App\Jobs\SupplementMp4LadderJob;
use App\Services\S3Service;
use App\Services\VideoMediaService;
use App\Services\VideoMp4FastStart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class VideosBackfillMediaCommand extends Command
{
    protected $signature = 'videos:backfill-media
                            {--posters : Regenerate thumb.webp for videos with a cover image}
                            {--transcode : Re-run HLS + MP4 transcode for published videos}
                            {--upgrade-ladder : Encode missing 720.mp4 / thumb_blur without changing transcode_status}
                            {--reencode-faststart : Re-mux existing MP4s with +faststart (keeps transcode_status ready)}
                            {--force : Queue every candidate (--reencode-faststart verifies in job; skips if already fast-start)}
                            {--heights=360,720 : MP4 heights for --reencode-faststart}
                            {--limit=50 : Max videos to queue per run}
                            {--dry-run : List work without dispatching jobs}';

    protected $description = 'Backfill CDN posters, transcodes, MP4 ladder gaps, and fast-start remux for existing videos';

    public function handle(S3Service $s3, VideoMp4FastStart $fastStart): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        if (($this->option('transcode') || $this->option('upgrade-ladder') || $this->option('reencode-faststart'))
            && ! $dryRun
            && ! $this->option('force')
            && $this->queueDepth() >= 80) {
            $this->info('Queue already has 80+ jobs; skipping dispatch (use --force to override)');

            return self::SUCCESS;
        }

        if ($this->option('upgrade-ladder')) {
            return $this->upgradeLadder($s3, $limit, $dryRun);
        }

        if ($this->option('reencode-faststart')) {
            return $this->reencodeFastStart($s3, $fastStart, $limit, $dryRun);
        }

        if (! $this->option('posters') && ! $this->option('transcode')) {
            $this->error('Specify at least one of --posters, --transcode, --upgrade-ladder, or --reencode-faststart');

            return self::FAILURE;
        }

        $query = DB::table('videos')
            ->where('status', 1)
            ->where('is_soft_delete', 0)
            ->whereNotNull('video')
            ->where('video', '!=', '');

        if ($this->option('transcode') && Schema::hasColumn('videos', 'transcode_status')) {
            $query->where(function ($builder) {
                $builder->where('transcode_status', 'pending')
                    ->orWhereNull('transcode_status');
            });
        }

        $query->orderByDesc('system_id')->limit($limit);

        $videos = $query->get(['id', 'video', 'image', 'processing_status', 'transcode_status']);
        $posterCount = 0;
        $transcodeCount = 0;

        foreach ($videos as $video) {
            if ($this->option('posters') && ! empty($video->image)) {
                $posterKey = VideoMediaService::posterKey((string) $video->id);
                if (! $s3->fileExists($posterKey)) {
                    $posterCount++;
                    if ($dryRun) {
                        $this->line("poster: {$video->id} (image={$video->image})");
                    } else {
                        $this->dispatchPosterJob($video);
                    }
                }
            }

            if ($this->option('transcode') && Schema::hasColumn('videos', 'transcode_status')) {
                $needsTranscode = ($video->transcode_status ?? 'pending') !== 'ready'
                    || ! $s3->fileExists(VideoMediaService::mp4Key((string) $video->id, 360));

                if ($needsTranscode) {
                    $transcodeCount++;
                    if ($dryRun) {
                        $this->line("transcode: {$video->id}");
                    } else {
                        ProcessVideoJob::dispatch((string) $video->id, (string) $video->video);
                    }
                }
            }
        }

        $this->info(sprintf(
            'Done. posters=%d transcodes=%d%s',
            $posterCount,
            $transcodeCount,
            $dryRun ? ' (dry-run)' : ''
        ));

        return self::SUCCESS;
    }

    /**
     * Encode missing ladder renditions without flipping transcode_status to pending.
     */
    private function upgradeLadder(S3Service $s3, int $limit, bool $dryRun): int
    {
        if (! Schema::hasColumn('videos', 'transcode_status')) {
            $this->warn('transcode_status column missing');

            return self::FAILURE;
        }

        $count = 0;

        foreach ($this->readyVideosCursor() as $video) {
            if ($count >= $limit) {
                break;
            }

            $id = (string) $video->id;
            $hlsHas720 = $s3->fileExists('videos/'.$id.'/hls/video_720.m3u8');
            $needs720 = $hlsHas720 && ! $s3->fileExists(VideoMediaService::mp4Key($id, 720));
            $needsBlur = ! $s3->fileExists(VideoMediaService::posterBlurKey($id));
            $needs360 = ! $s3->fileExists(VideoMediaService::mp4Key($id, 360));

            if (! $needs720 && ! $needsBlur && ! $needs360) {
                continue;
            }

            $count++;

            if ($dryRun) {
                $this->line("upgrade-ladder: {$id} (720=".($needs720 ? 'missing' : 'ok').', blur='.($needsBlur ? 'missing' : 'ok').', 360='.($needs360 ? 'missing' : 'ok').')');

                continue;
            }

            SupplementMp4LadderJob::dispatch($id, (string) $video->video);
        }

        $this->info(sprintf('Upgrade ladder: %d video(s)%s', $count, $dryRun ? ' (dry-run)' : ' queued'));

        return self::SUCCESS;
    }

    /**
     * Re-mux existing MP4 renditions with moov before mdat; transcode_status stays ready.
     */
    private function reencodeFastStart(S3Service $s3, VideoMp4FastStart $fastStart, int $limit, bool $dryRun): int
    {
        if (! Schema::hasColumn('videos', 'transcode_status')) {
            $this->warn('transcode_status column missing');

            return self::FAILURE;
        }

        $heights = $this->parseHeights((string) $this->option('heights'));
        $force = (bool) $this->option('force');
        $count = 0;
        $skipped = 0;

        foreach ($this->readyVideosCursor() as $video) {
            if ($count >= $limit) {
                break;
            }

            $id = (string) $video->id;
            $hasMp4 = false;
            $needsWork = $force;
            $reasons = $force ? ['force:verify-in-job'] : [];

            foreach ($heights as $height) {
                $key = VideoMediaService::mp4Key($id, $height);
                if (! $s3->fileExists($key)) {
                    continue;
                }

                $hasMp4 = true;

                if ($force) {
                    continue;
                }

                $appears = $fastStart->appearsFastStartOnStorage($key);
                if ($appears === false) {
                    $needsWork = true;
                    $reasons[] = "{$height}p:moov-at-end";
                } elseif ($appears === null) {
                    $needsWork = true;
                    $reasons[] = "{$height}p:check-full";
                }
            }

            if (! $hasMp4 || ! $needsWork) {
                $skipped++;

                continue;
            }

            $count++;

            if ($dryRun) {
                $this->line("reencode-faststart: {$id} (".implode(', ', $reasons).')');

                continue;
            }

            ReencodeFastStartJob::dispatch($id, $heights);
        }

        $this->info(sprintf(
            'Fast-start remux: %d video(s) queued, %d already fast-start%s',
            $count,
            $skipped,
            $dryRun ? ' (dry-run)' : ''
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function parseHeights(string $value): array
    {
        $heights = array_values(array_unique(array_filter(array_map(
            'intval',
            explode(',', $value)
        ), static fn (int $h) => $h > 0)));

        return $heights !== [] ? $heights : [360, 720];
    }

    /**
     * @return \Generator<int, object>
     */
    private function readyVideosCursor(): \Generator
    {
        $query = DB::table('videos')
            ->where('status', 1)
            ->where('is_soft_delete', 0)
            ->where('transcode_status', 'ready')
            ->whereNotNull('video')
            ->where('video', '!=', '')
            ->orderByDesc('system_id');

        foreach ($query->cursor() as $video) {
            yield $video;
        }
    }

    private function queueDepth(): int
    {
        return (int) Redis::llen('queues:video-processing');
    }

    private function dispatchPosterJob(object $video): void
    {
        $imageName = basename(str_replace('\\', '/', (string) $video->image));
        $sourceKey = str_starts_with((string) $video->image, 'videos/')
            ? (string) $video->image
            : 'videos/'.$imageName;

        $s3 = app(S3Service::class);
        if (! $s3->fileExists($sourceKey)) {
            $this->warn("Skip poster {$video->id}: source missing {$sourceKey}");

            return;
        }

        $stagingDir = storage_path('app/temp-thumbnails/staging');
        if (! is_dir($stagingDir)) {
            mkdir($stagingDir, 0755, true);
        }

        $stagingPath = $stagingDir.'/backfill_'.$video->id.'_'.$imageName;
        file_put_contents($stagingPath, $s3->retrieveFile($sourceKey));

        ProcessVideoThumbnailJob::dispatch((string) $video->id, $stagingPath, $imageName);
    }
}
