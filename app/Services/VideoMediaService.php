<?php

namespace App\Services;

use App\Support\CookCache;
use App\Support\CdnUrl;

class VideoMediaService
{
    public static function posterKey(string $videoId): string
    {
        return 'videos/'.$videoId.'/thumb.webp';
    }

    public static function posterBlurKey(string $videoId): string
    {
        return 'videos/'.$videoId.'/thumb_blur.webp';
    }

    public static function hlsMasterKey(string $videoId): string
    {
        return 'videos/'.$videoId.'/hls/master.m3u8';
    }

    public static function mp4Key(string $videoId, int $height): string
    {
        return 'videos/'.$videoId.'/'.$height.'.mp4';
    }

    /**
     * @return array{url_360: ?string, url_720: ?string, url_1080: ?string}
     */
    public static function videoSources(string $videoId, bool $isReady, ?S3Service $s3 = null): array
    {
        if (! $isReady) {
            return [
                'url_360' => null,
                'url_720' => null,
                'url_1080' => null,
            ];
        }

        $s3 ??= app(S3Service::class);

        return [
            'url_360' => self::mp4UrlIfExists($videoId, 360, $s3),
            'url_720' => self::mp4UrlIfExists($videoId, 720, $s3),
            'url_1080' => self::mp4UrlIfExists($videoId, 1080, $s3),
        ];
    }

    private static function mp4UrlIfExists(string $videoId, int $height, S3Service $s3): ?string
    {
        $key = self::mp4Key($videoId, $height);

        $exists = CookCache::remember(
            'video:mp4_exists:'.$videoId.':'.$height,
            [300, 3600],
            fn () => $s3->fileExists($key)
        );

        if (! $exists) {
            return null;
        }

        return CdnUrl::forPath($key);
    }

    /** Bust cached existence checks after upload/re-transcode. */
    public static function forgetMp4ExistsCache(string $videoId): void
    {
        foreach ([360, 720, 1080] as $height) {
            CookCache::forget('video:mp4_exists:'.$videoId.':'.$height);
        }
    }

    public static function resolveStorageKey(?string $stored, ?string $defaultKey = null): ?string
    {
        if ($stored === null || trim($stored) === '') {
            return $defaultKey;
        }

        $stored = trim($stored);

        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            $path = parse_url($stored, PHP_URL_PATH);

            return $path !== null && $path !== '' ? ltrim($path, '/') : $defaultKey;
        }

        return ltrim(str_replace('\\', '/', $stored), '/');
    }

    public static function resolvePosterUrl(
        string $videoId,
        ?string $imageFilename,
        ?string $processingStatus,
    ): ?string {
        if (($processingStatus ?? '') === 'ready') {
            return CdnUrl::forPath(self::posterKey($videoId));
        }

        if ($imageFilename === null || trim($imageFilename) === '') {
            return null;
        }

        $image = trim($imageFilename);
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        $basename = basename(str_replace('\\', '/', $image));
        if ($basename !== '') {
            $legacyThumb = CdnUrl::forPath('videos/thumbnail/'.$basename);
            if ($legacyThumb !== null) {
                return $legacyThumb;
            }
        }

        return self::resolveCoverImageUrl($imageFilename);
    }

    public static function resolvePosterBlurUrl(string $videoId, ?string $processingStatus): ?string
    {
        if (($processingStatus ?? '') !== 'ready') {
            return null;
        }

        return CdnUrl::forPath(self::posterBlurKey($videoId));
    }

    public static function resolveCoverImageUrl(?string $imageFilename): ?string
    {
        if ($imageFilename === null || trim($imageFilename) === '') {
            return null;
        }

        $image = trim($imageFilename);
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        $key = str_starts_with($image, 'videos/') ? $image : 'videos/'.$image;

        return CdnUrl::forPath($key);
    }

    public static function resolveHlsKey(?string $hlsUrl, string $videoId, bool $isReady): ?string
    {
        if (! $isReady) {
            return null;
        }

        return self::resolveStorageKey($hlsUrl, self::hlsMasterKey($videoId));
    }
}
