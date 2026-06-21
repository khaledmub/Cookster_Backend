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
        ?string $transcodeStatus = null,
        ?S3Service $s3 = null,
    ): ?string {
        $s3 ??= app(S3Service::class);
        $posterKey = self::posterKey($videoId);

        if ((($transcodeStatus ?? '') === 'ready' || ($processingStatus ?? '') === 'ready')
            && $s3->fileExists($posterKey)) {
            return CdnUrl::forPath($posterKey);
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

    public static function resolvePosterBlurUrl(
        string $videoId,
        ?string $processingStatus,
        ?string $transcodeStatus = null,
        ?S3Service $s3 = null,
    ): ?string {
        $s3 ??= app(S3Service::class);
        $blurKey = self::posterBlurKey($videoId);

        if ((($transcodeStatus ?? '') === 'ready' || ($processingStatus ?? '') === 'ready')
            && $s3->fileExists($blurKey)) {
            return CdnUrl::forPath($blurKey);
        }

        return null;
    }

    /**
     * @param  array{url_360: ?string, url_720: ?string, url_1080: ?string}  $videoSources
     */
    public static function pickBestLadderMp4Url(array $videoSources): ?string
    {
        foreach (['url_1080', 'url_720', 'url_360'] as $key) {
            $url = $videoSources[$key] ?? null;
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    public static function isStaticImageUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? $url;

        return (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', $path);
    }

    /**
     * Set video_url / video from MP4 ladder for ready video posts. Never JPG in video_url.
     *
     * @param  array{url_360: ?string, url_720: ?string, url_1080: ?string}  $videoSources
     */
    public static function applyVideoPlaybackUrls(
        object $row,
        array $videoSources,
        bool $isPhotoPost,
        bool $isHlsReady,
        ?string $legacyVideoUrl = null,
    ): void {
        if ($isPhotoPost) {
            return;
        }

        $best = self::pickBestLadderMp4Url($videoSources);

        if ($isHlsReady && $best !== null) {
            $row->video_url = $best;

            return;
        }

        if ($legacyVideoUrl !== null && ! self::isStaticImageUrl($legacyVideoUrl)) {
            $row->video_url = $legacyVideoUrl;

            return;
        }

        if (self::isStaticImageUrl($row->video_url ?? null)) {
            $row->video_url = $best;
        }
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

    public static function isStaticImageFilename(?string $filename): bool
    {
        if ($filename === null || trim($filename) === '') {
            return false;
        }

        $ext = strtolower(pathinfo(basename(str_replace('\\', '/', trim($filename))), PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    /**
     * Full-resolution photo URL for is_image posts (videos/{cover}.jpg).
     */
    public static function resolvePhotoFullImageUrl(?string $imageFilename, ?string $videoFilename = null): ?string
    {
        if ($imageFilename !== null && trim($imageFilename) !== '' && self::isStaticImageFilename($imageFilename)) {
            return self::resolveCoverImageUrl($imageFilename);
        }

        if ($videoFilename !== null && trim($videoFilename) !== '' && self::isStaticImageFilename($videoFilename)) {
            return self::resolveCoverImageUrl($videoFilename);
        }

        if ($imageFilename !== null && trim($imageFilename) !== '') {
            return self::resolveCoverImageUrl($imageFilename);
        }

        return null;
    }

    /**
     * Legacy small thumb for photo posts (videos/thumbnail/{cover}.jpg).
     */
    public static function resolvePhotoThumbnailUrl(?string $imageFilename): ?string
    {
        if ($imageFilename === null || trim($imageFilename) === '') {
            return null;
        }

        $basename = basename(str_replace('\\', '/', trim($imageFilename)));
        if ($basename === '') {
            return null;
        }

        return CdnUrl::forPath('videos/thumbnail/'.$basename);
    }

    public static function resolveHlsKey(?string $hlsUrl, string $videoId, bool $isReady): ?string
    {
        if (! $isReady) {
            return null;
        }

        return self::resolveStorageKey($hlsUrl, self::hlsMasterKey($videoId));
    }
}
