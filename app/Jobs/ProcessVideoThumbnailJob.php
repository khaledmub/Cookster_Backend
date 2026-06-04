<?php

namespace App\Jobs;

use App\Services\S3Service;
use App\Services\VideoMediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Intervention\Image\Laravel\Facades\Image;

class ProcessVideoThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public string $videoId,
        public string $localImagePath,
        public string $imageName,
    ) {
        $this->onQueue('thumbnails');
    }

    public function handle(S3Service $s3Service): void
    {
        if (! file_exists($this->localImagePath)) {
            return;
        }

        $thumbnailLocalPath = storage_path('app/temp-thumbnails');
        if (! file_exists($thumbnailLocalPath)) {
            mkdir($thumbnailLocalPath, 0755, true);
        }

        $posterPath = $thumbnailLocalPath.'/'.$this->videoId.'_poster.webp';
        $blurPath = $thumbnailLocalPath.'/'.$this->videoId.'_blur.webp';
        $legacyJpegPath = $thumbnailLocalPath.'/'.$this->imageName;

        $img = Image::read($this->localImagePath);
        $img->scaleDown(width: 720);
        $img->toWebp(quality: 82)->save($posterPath);

        $blur = Image::read($this->localImagePath);
        $blur->cover(32, 32);
        $blur->toWebp(quality: 60)->save($blurPath);

        $legacy = Image::read($this->localImagePath);
        $legacy->resize(100, 100, function ($constraint) {
            $constraint->aspectRatio();
        })->save($legacyJpegPath);

        $s3Service->storeFile(VideoMediaService::posterKey($this->videoId), file_get_contents($posterPath), [
            'mimetype' => 'image/webp',
        ]);

        $s3Service->storeFile(VideoMediaService::posterBlurKey($this->videoId), file_get_contents($blurPath), [
            'mimetype' => 'image/webp',
        ]);

        $s3Service->storeFile('videos/thumbnail/'.$this->imageName, file_get_contents($legacyJpegPath), [
            'mimetype' => S3Service::resolveMimeType($legacyJpegPath, 'image/jpeg'),
        ]);

        if (! $s3Service->fileExists(VideoMediaService::posterKey($this->videoId))) {
            throw new \RuntimeException('Poster upload missing on object storage: '.$this->videoId);
        }

        foreach ([$posterPath, $blurPath, $legacyJpegPath] as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        if (file_exists($this->localImagePath)) {
            unlink($this->localImagePath);
        }

        $this->updateProcessingStatus('ready');
    }

    public function failed(): void
    {
        $this->updateProcessingStatus('failed');
    }

    private function updateProcessingStatus(string $status): void
    {
        if (! Schema::hasColumn('videos', 'processing_status')) {
            return;
        }

        DB::table('videos')
            ->where('id', $this->videoId)
            ->update(['processing_status' => $status]);
    }
}
