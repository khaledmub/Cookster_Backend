<?php

namespace App\Services;

use RuntimeException;

class VideoMediaVerifier
{
    /**
     * Required object keys before transcode_status may be set to "ready".
     *
     * @return list<string>
     */
    public static function requiredTranscodeKeys(string $videoId, ?array $mp4Heights = null): array
    {
        $keys = [VideoMediaService::hlsMasterKey($videoId)];

        foreach ($mp4Heights ?? [360] as $height) {
            $keys[] = VideoMediaService::mp4Key($videoId, (int) $height);
        }

        return $keys;
    }

    /**
     * Optional renditions — present on storage when encoded, not required for "ready".
     *
     * @return list<string>
     */
    public static function optionalTranscodeKeys(string $videoId): array
    {
        return [
            VideoMediaService::mp4Key($videoId, 720),
            VideoMediaService::mp4Key($videoId, 1080),
        ];
    }

    /**
     * MP4 heights required for "ready". Mobile preloads 360 + 720 fast-start MP4.
     *
     * @return list<int>
     */
    public function resolveRequiredMp4Heights(string $videoId, S3Service $s3Service, ?array $mp4LadderHeights = null): array
    {
        if ($mp4LadderHeights !== null && $mp4LadderHeights !== []) {
            return array_values(array_unique(array_map('intval', $mp4LadderHeights)));
        }

        $configured = config('ffmpeg.mp4_ladder_heights');
        $heights = is_array($configured) && $configured !== []
            ? array_map('intval', $configured)
            : [360, 720];

        return array_values(array_unique(array_filter($heights, static fn (int $h) => $h > 0)));
    }

    /**
     * Poster keys required before transcode_status may be set to "ready".
     *
     * @return list<string>
     */
    public static function requiredPosterKeys(string $videoId): array
    {
        return [
            VideoMediaService::posterKey($videoId),
            VideoMediaService::posterBlurKey($videoId),
        ];
    }

    /**
     * @return list<string> Keys that were expected but missing on object storage.
     */
    public function missingTranscodeKeys(string $videoId, S3Service $s3Service, ?array $mp4LadderHeights = null): array
    {
        $mp4Heights = $this->resolveRequiredMp4Heights($videoId, $s3Service, $mp4LadderHeights);
        $missing = [];

        foreach (self::requiredTranscodeKeys($videoId, $mp4Heights) as $key) {
            if (! $s3Service->fileExists($key)) {
                $missing[] = $key;
            }
        }

        foreach (self::requiredPosterKeys($videoId) as $key) {
            if (! $s3Service->fileExists($key)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * @throws RuntimeException
     */
    public function assertTranscodeReady(string $videoId, S3Service $s3Service, ?array $mp4LadderHeights = null): void
    {
        $missing = $this->missingTranscodeKeys($videoId, $s3Service, $mp4LadderHeights);
        if ($missing !== []) {
            throw new RuntimeException(
                'Transcode artifacts missing on object storage: '.implode(', ', $missing)
            );
        }
    }
}
