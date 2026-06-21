<?php

namespace App\Jobs;

use App\Services\S3Service;
use App\Services\VideoMediaService;
use App\Services\VideoMp4FastStart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ReencodeFastStartJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout;

    /**
     * @param  list<int>  $heights
     */
    public function __construct(
        public string $videoId,
        public array $heights = [360, 720, 1080],
    ) {
        $this->onQueue('video-processing');
        $this->timeout = (int) config('ffmpeg.timeout', 7200);
    }

    public function handle(S3Service $s3, VideoMp4FastStart $fastStart): void
    {
        foreach ($this->heights as $height) {
            $this->processHeight($s3, $fastStart, (int) $height);
        }
    }

    private function processHeight(S3Service $s3, VideoMp4FastStart $fastStart, int $height): void
    {
        $key = VideoMediaService::mp4Key($this->videoId, $height);
        if (! $s3->fileExists($key)) {
            return;
        }

        $appears = $fastStart->appearsFastStartOnStorage($key);
        if ($appears === true) {
            return;
        }

        $workDir = storage_path('app/faststart/'.$this->videoId.'/'.$height);
        $inputPath = $workDir.'/input.mp4';
        $outputPath = $workDir.'/output.mp4';
        $stagingKey = 'videos/'.$this->videoId.'/_staging/'.$height.'.mp4';

        try {
            if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
                throw new RuntimeException('Unable to create fast-start work directory: '.$workDir);
            }

            file_put_contents($inputPath, $s3->retrieveFile($key));

            if ($appears === null && $fastStart->isFastStart($inputPath)) {
                return;
            }

            $fastStart->remuxFastStart($inputPath, $outputPath);

            if (! $fastStart->isFastStart($outputPath)) {
                throw new RuntimeException("Fast-start remux verification failed for {$key}");
            }

            $s3->storeFileFromPath($stagingKey, $outputPath, ['mimetype' => 'video/mp4']);
            Storage::disk('s3')->copy($stagingKey, $key);
            $s3->deleteFile($stagingKey);

            VideoMediaService::forgetMp4ExistsCache($this->videoId);

            Log::info('ReencodeFastStartJob completed', [
                'video_id' => $this->videoId,
                'height' => $height,
            ]);
        } finally {
            $this->cleanupDirectory($workDir);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ReencodeFastStartJob failed', [
            'video_id' => $this->videoId,
            'heights' => $this->heights,
            'message' => $exception->getMessage(),
        ]);
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
