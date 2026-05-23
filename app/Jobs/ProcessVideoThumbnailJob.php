<?php

namespace App\Jobs;

use App\Services\S3Service;
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

    public function __construct(
        public string $videoId,
        public string $localImagePath,
        public string $imageName,
    ) {}

    public function handle(S3Service $s3Service): void
    {
        if (! file_exists($this->localImagePath)) {
            return;
        }

        $thumbnailLocalPath = storage_path('app/temp-thumbnails');
        if (! file_exists($thumbnailLocalPath)) {
            mkdir($thumbnailLocalPath, 0755, true);
        }

        $thumbnailFullLocalPath = $thumbnailLocalPath.'/'.$this->imageName;

        $img = Image::read($this->localImagePath);
        $img->resize(100, 100, function ($constraint) {
            $constraint->aspectRatio();
        })->save($thumbnailFullLocalPath);

        $s3Service->storeFile('videos/thumbnail/'.$this->imageName, file_get_contents($thumbnailFullLocalPath), [
            'mimetype' => S3Service::resolveMimeType($thumbnailFullLocalPath, 'image/jpeg'),
        ]);

        if (file_exists($thumbnailFullLocalPath)) {
            unlink($thumbnailFullLocalPath);
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
