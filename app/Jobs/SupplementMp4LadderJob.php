<?php

namespace App\Jobs;

use App\Services\S3Service;
use App\Services\VideoMediaService;
use App\Services\VideoMp4Transcoder;
use App\Services\VideoPosterExtractor;
use App\Services\VideoProbeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Adds missing MP4 ladder renditions and blur poster without changing transcode_status.
 */
class SupplementMp4LadderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout;

    public function __construct(
        public string $videoId,
        public string $videoFilename,
    ) {
        $this->onQueue('video-processing');
        $this->timeout = (int) config('ffmpeg.timeout', 7200);
    }

    public function handle(
        VideoMp4Transcoder $mp4Transcoder,
        VideoPosterExtractor $posterExtractor,
        VideoProbeService $probeService,
        S3Service $s3Service,
    ): void {
        $video = DB::table('videos')->where('id', $this->videoId)->first();
        if (! $video || empty($video->video)) {
            return;
        }

        $workDir = storage_path('app/mp4-supplement/'.$this->videoId);
        $mp4Dir = $workDir.'/mp4';
        $sourcePath = $workDir.'/source.mp4';

        try {
            if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
                throw new RuntimeException('Unable to create supplement work directory: '.$workDir);
            }

            $this->downloadSource($s3Service, (string) $video->video, $sourcePath);

            $sourceHeight = $probeService->probeVideoHeight($sourcePath);
            $mp4LadderHeights = $probeService->mp4LadderHeightsForSource($sourceHeight);

            $missingHeights = [];
            foreach ($mp4LadderHeights as $height) {
                if (! $s3Service->fileExists(VideoMediaService::mp4Key($this->videoId, $height))) {
                    $missingHeights[] = $height;
                }
            }

            if ($missingHeights !== []) {
                $outputs = $mp4Transcoder->transcode($sourcePath, $mp4Dir, $missingHeights);
                foreach ($outputs as $height => $localPath) {
                    $remoteKey = VideoMediaService::mp4Key($this->videoId, (int) $height);
                    $s3Service->storeFileFromPath($remoteKey, $localPath, ['mimetype' => 'video/mp4']);
                }
                VideoMediaService::forgetMp4ExistsCache($this->videoId);
            }

            $posterKey = VideoMediaService::posterKey($this->videoId);
            $blurKey = VideoMediaService::posterBlurKey($this->videoId);

            if (! $s3Service->fileExists($blurKey) || ! $s3Service->fileExists($posterKey)) {
                $posterDir = $workDir.'/poster';
                $extracted = $posterExtractor->extract($sourcePath, $posterDir);

                if (! $s3Service->fileExists($posterKey)) {
                    $s3Service->storeFileFromPath($posterKey, $extracted['poster'], ['mimetype' => 'image/webp']);
                }
                if (! $s3Service->fileExists($blurKey)) {
                    $s3Service->storeFileFromPath($blurKey, $extracted['blur'], ['mimetype' => 'image/webp']);
                }

                if (Schema::hasColumn('videos', 'processing_status')) {
                    DB::table('videos')
                        ->where('id', $this->videoId)
                        ->update(['processing_status' => 'ready']);
                }
            }

            Log::info('SupplementMp4LadderJob completed', [
                'video_id' => $this->videoId,
                'encoded_heights' => $missingHeights,
            ]);
        } catch (Throwable $e) {
            Log::warning('SupplementMp4LadderJob failed', [
                'video_id' => $this->videoId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $this->cleanupDirectory($workDir);
        }
    }

    private function downloadSource(S3Service $s3Service, string $filename, string $destination): void
    {
        $key = str_starts_with($filename, 'videos/') ? $filename : 'videos/'.$filename;

        if (! $s3Service->fileExists($key)) {
            throw new RuntimeException('Source video not found on object storage: '.$key);
        }

        $readStream = Storage::disk('s3')->readStream($key);
        if ($readStream === null) {
            throw new RuntimeException('Unable to open source video stream: '.$key);
        }

        $writeStream = fopen($destination, 'wb');
        if ($writeStream === false) {
            if (is_resource($readStream)) {
                fclose($readStream);
            }
            throw new RuntimeException('Unable to write source video to disk: '.$destination);
        }

        try {
            stream_copy_to_stream($readStream, $writeStream);
        } finally {
            if (is_resource($readStream)) {
                fclose($readStream);
            }
            if (is_resource($writeStream)) {
                fclose($writeStream);
            }
        }

        if (! is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException('Downloaded source video is empty: '.$key);
        }
    }

    private function cleanupDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir.'/'.$entry;
            if (is_dir($path)) {
                $this->cleanupDirectory($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        @rmdir($dir);
    }
}
