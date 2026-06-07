<?php
namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class S3Service
{
    /** Resolved only when an operation runs (ApiController injects this on every API request). */
    private ?Filesystem $resolvedDisk = null;

    private function requireBucketConfigured(): void
    {
        $bucket = (string) (config('filesystems.disks.s3.bucket') ?? '');
        if ($bucket === '') {
            throw new RuntimeException(
                'Object storage bucket is not configured. Set AWS_BUCKET in .env (e.g. GCS HMAC: AWS_ENDPOINT=https://storage.googleapis.com, AWS_USE_PATH_STYLE_ENDPOINT=true), or add AWS_BUCKET to the cooksterS3bucket secret JSON in production.'
            );
        }
    }

    private function disk(): Filesystem
    {
        $this->requireBucketConfigured();

        return $this->resolvedDisk ??= Storage::disk('s3');
    }

    /**
     * @param  array<string, mixed>  $options  Flysystem / S3 adapter options, e.g. ['mimetype' => 'video/mp4']
     */
    public function storeFile(string $filename, $contents, array $options = []): bool
    {
        if (! isset($options['CacheControl'])) {
            $options['CacheControl'] = $this->defaultCacheControl($filename);
        }

        return (bool) $this->disk()->put($filename, $contents, $options);
    }

    /**
     * Stream a local file to object storage without loading it entirely into memory.
     */
    public function storeFileFromPath(string $filename, string $localPath, array $options = []): bool
    {
        if (! is_file($localPath)) {
            throw new RuntimeException('Local file not found for upload: '.$localPath);
        }

        if (! isset($options['CacheControl'])) {
            $options['CacheControl'] = $this->defaultCacheControl($filename);
        }

        if (! isset($options['mimetype'])) {
            $options['mimetype'] = self::resolveMimeType($localPath);
        }

        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open local file for upload: '.$localPath);
        }

        try {
            return (bool) $this->disk()->put($filename, $stream, $options);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function defaultCacheControl(string $filename): string
    {
        if (str_ends_with($filename, '.m3u8')) {
            return 'public, max-age=300';
        }

        if (preg_match('#/(thumb(_blur)?\.webp|360|720|1080)\.mp4$#', $filename)
            || preg_match('#\.(ts|m4s)$#', $filename)) {
            return 'public, max-age=31536000, immutable';
        }

        if (preg_match('#\.mp4$#', $filename)) {
            return 'public, max-age=31536000, immutable';
        }

        return 'public, max-age=86400';
    }

    /**
     * Best-effort MIME for PutObject Content-Type (GCS/S3 interoperability).
     *
     * @param  UploadedFile|string  $fileOrPath
     */
    public static function resolveMimeType($fileOrPath, string $fallback = 'application/octet-stream'): string
    {
        if ($fileOrPath instanceof UploadedFile) {
            $mime = $fileOrPath->getMimeType();
            if ($mime && $mime !== 'application/octet-stream') {
                return $mime;
            }

            return self::mimeFromExtension($fileOrPath->getClientOriginalExtension(), $fallback);
        }

        if (is_string($fileOrPath) && is_file($fileOrPath)) {
            $mime = @mime_content_type($fileOrPath);
            if ($mime && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }

        return $fallback;
    }

    private static function mimeFromExtension(?string $ext, string $fallback): string
    {
        if ($ext === null || $ext === '') {
            return $fallback;
        }

        $e = strtolower(ltrim($ext, '.'));

        return match ($e) {
            'mp4', 'm4v' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'aac' => 'audio/aac',
            'ogg' => 'audio/ogg',
            'm4a' => 'audio/mp4',
            'flac' => 'audio/flac',
            'wma' => 'audio/x-ms-wma',
            default => $fallback,
        };
    }

    public function retrieveFile($filename)
    {
        return $this->disk()->get($filename);
    }

    public function fileExists($filename)
    {
        return $this->disk()->exists($filename);
    }

    public function deleteFile($filename)
    {
        return $this->disk()->delete($filename);
    }

    public function generateTemporaryUrl($filename, $duration)
    {
        return $this->disk()->temporaryUrl($filename, now()->addMinutes($duration));
    }
}
