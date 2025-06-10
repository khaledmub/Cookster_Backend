<?php
namespace App\Services;

use Illuminate\Support\Facades\Storage;

class S3Service
{
    private $disk;
    
    public function __construct()
    {
        $this->disk = Storage::disk('s3');
    }

    public function storeFile($filename, $contents)
    {
        return $this->disk->put($filename, $contents);
     }

    public function retrieveFile($filename)
    {
        return $this->disk->get($filename);
    }

    public function fileExists($filename)
    {
        return $this->disk->exists($filename);
    }

    public function deleteFile($filename)
    {
        return $this->disk->delete($filename);
    }

    public function generateTemporaryUrl($filename, $duration)
    {
        return $this->disk->temporaryUrl($filename, now()->addMinutes($duration));
    }
}