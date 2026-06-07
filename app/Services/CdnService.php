<?php

namespace App\Services;

use Aws\CloudFront\UrlSigner;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CdnService
{
    public function isCloudFrontSigningEnabled(): bool
    {
        if (! config('cdn.cloudfront.enabled')) {
            return false;
        }

        return $this->cloudFrontKeyPairId() !== ''
            && is_readable((string) config('cdn.cloudfront.private_key_path'));
    }

    /**
     * Public or signed URL for a storage object key (e.g. videos/foo.mp4).
     */
    public function urlForPath(?string $path, bool $signed = false, ?int $ttlSeconds = null): ?string
    {
        $normalized = $this->normalizePath($path);
        if ($normalized === null) {
            return null;
        }

        if ($signed) {
            return $this->generateSignedUrl($normalized, $ttlSeconds);
        }

        if ($this->shouldUseCdn()) {
            $cdn = $this->publicCdnUrlForPath($normalized);
            if ($cdn !== null) {
                return $cdn;
            }
        }

        return $this->objectStoragePublicUrlForPath($normalized);
    }

    /**
     * Always the direct GCS/S3 public URL (never the CDN hostname).
     */
    public function directUrlForPath(?string $path): ?string
    {
        $normalized = $this->normalizePath($path);

        return $normalized === null ? null : $this->objectStoragePublicUrlForPath($normalized);
    }

    public function shouldUseCdn(): bool
    {
        if (config('cdn.force_direct_storage')) {
            return false;
        }

        if (! config('cdn.enabled')) {
            return false;
        }

        return rtrim((string) config('cdn.base_url'), '/') !== '';
    }

    /** Public GCS/S3 base without trailing path segment (e.g. …/cookster-storage-v1). */
    public function directPublicBaseUrl(): string
    {
        $base = $this->objectStoragePublicBase();

        return $base === null ? '' : rtrim($base, '/');
    }

    /**
     * CloudFront signed URL, or S3/GCS temporary/public URL when signing is off.
     */
    public function generateSignedUrl(string $path, ?int $ttlSeconds = null): string
    {
        $normalized = $this->normalizePath($path);
        if ($normalized === null) {
            throw new RuntimeException('Cannot sign an empty object path.');
        }

        $ttl = $ttlSeconds ?? (int) config('cdn.cloudfront.url_ttl', 86400);

        if ($this->isCloudFrontSigningEnabled()) {
            return $this->cloudFrontSignedUrl($normalized, $ttl);
        }

        return $this->objectStorageSignedOrPublicUrl($normalized, $ttl);
    }

    public function publicCdnUrlForPath(string $path): ?string
    {
        $base = rtrim((string) config('cdn.base_url'), '/');
        if ($base === '') {
            return null;
        }

        return $base.'/'.ltrim($path, '/');
    }

    public function objectStoragePublicUrlForPath(string $path): ?string
    {
        $base = $this->objectStoragePublicBase();
        if ($base === null) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        return rtrim($base, '/').'/'.$path;
    }

    private function cloudFrontSignedUrl(string $path, int $ttlSeconds): string
    {
        $domain = rtrim((string) config('cdn.cloudfront.domain'), '/');
        $cdnBase = $domain !== '' ? $domain : rtrim((string) config('cdn.base_url'), '/');

        if ($cdnBase === '') {
            throw new RuntimeException('CloudFront domain or CDN_URL is not configured.');
        }

        $unsigned = $cdnBase.'/'.ltrim($path, '/');
        $expires = time() + $ttlSeconds;

        $signer = new UrlSigner(
            $this->cloudFrontKeyPairId(),
            (string) config('cdn.cloudfront.private_key_path')
        );

        return $signer->getSignedUrl($unsigned, $expires);
    }

    private function objectStorageSignedOrPublicUrl(string $path, int $ttlSeconds): string
    {
        try {
            $disk = Storage::disk('s3');

            if (method_exists($disk, 'temporaryUrl')) {
                return $disk->temporaryUrl($path, now()->addSeconds($ttlSeconds));
            }
        } catch (\Throwable) {
            // Fall through to public URL.
        }

        $public = $this->objectStoragePublicUrlForPath($path);
        if ($public !== null) {
            return $public;
        }

        throw new RuntimeException(
            'No CDN or object storage URL available. Set CDN_URL / AWS_URL / AWS_CLOUD_FRONT_PATH or enable CloudFront signing.'
        );
    }

    private function objectStoragePublicBase(): ?string
    {
        $bucket = (string) (config('filesystems.disks.s3.bucket') ?? '');
        $endpoint = rtrim((string) (config('filesystems.disks.s3.endpoint') ?? ''), '/');

        // Prefer GCS origin from endpoint + bucket (not AWS_URL, which may point at CDN).
        if ($bucket !== '' && $endpoint !== '' && str_contains(strtolower($endpoint), 'googleapis.com')) {
            return $endpoint.'/'.$bucket.'/';
        }

        $base = rtrim((string) (config('filesystems.disks.s3.cloudfront_path') ?? ''), '/');
        if ($base === '') {
            $base = rtrim((string) (config('filesystems.disks.s3.url') ?? ''), '/');
        }

        if ($bucket !== '' && preg_match('#^https?://storage\.googleapis\.com/?$#i', $base)) {
            $base = rtrim($base, '/').'/'.$bucket;
        }

        // Do not treat the CDN hostname as "direct" storage.
        $cdnHost = parse_url((string) config('cdn.base_url'), PHP_URL_HOST);
        $baseHost = parse_url($base, PHP_URL_HOST);
        if ($cdnHost && $baseHost && strcasecmp($cdnHost, $baseHost) === 0) {
            if ($bucket !== '' && $endpoint !== '') {
                return $endpoint.'/'.$bucket.'/';
            }
        }

        return $base === '' ? null : $base.'/';
    }

    private function cloudFrontKeyPairId(): string
    {
        return trim((string) config('cdn.cloudfront.key_pair_id'));
    }

    private function normalizePath(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return ltrim(str_replace('\\', '/', $path), '/');
    }
}
