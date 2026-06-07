<?php

namespace App\Support;

use App\Services\CdnService;

class CdnUrl
{
    /**
     * Resolve a media URL: CDN → S3/GCS public URL (full fallback when CDN is unset).
     */
    public static function forPath(?string $path, bool $signed = false): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return app(CdnService::class)->urlForPath($path, $signed);
    }
}
