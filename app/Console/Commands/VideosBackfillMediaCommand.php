<?php

namespace App\Console\Commands;

use App\Jobs\ProcessVideoJob;
use App\Jobs\ProcessVideoThumbnailJob;
use App\Services\S3Service;
use App\Services\VideoMediaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VideosBackfillMediaCommand extends Command
{
    protected $signature = 'videos:backfill-media
                            {--posters : Regenerate thumb.webp for videos with a cover image}
                            {--transcode : Re-run HLS + MP4 transcode for published videos}
                            {--limit=50 : Max videos per run}
                            {--dry-run : List work without dispatching jobs}';

    protected $description = 'Backfill CDN posters (thumb.webp) and/or transcodes for existing videos';

    public function handle(S3Service $s3): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        if (! $this->option('posters') && ! $this->option('transcode')) {
            $this->error('Specify at least one of --posters or --transcode');

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
                    ->orWhere('transcode_status', 'failed')
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
