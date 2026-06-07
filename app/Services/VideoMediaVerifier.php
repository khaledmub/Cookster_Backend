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
    public static function requiredTranscodeKeys(string $videoId): array
    {
        return [
            VideoMediaService::hlsMasterKey($videoId),
            VideoMediaService::mp4Key($videoId, 360),
            VideoMediaService::mp4Key($videoId, 720),
        ];
    }

    /**
     * Optional renditions (e.g. 1080) — verified when present, not required for "ready".
     *
     * @return list<string>
     */
    public static function optionalTranscodeKeys(string $videoId): array
    {
        return [
            VideoMediaService::mp4Key($videoId, 1080),
        ];
    }

    /**
     * @return list<string> Keys that were expected but missing on object storage.
     */
    public function missingTranscodeKeys(string $videoId, S3Service $s3Service): array
    {
        $missing = [];

        foreach (self::requiredTranscodeKeys($videoId) as $key) {
            if (! $s3Service->fileExists($key)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * @throws RuntimeException
     */
    public function assertTranscodeReady(string $videoId, S3Service $s3Service): void
    {
        $missing = $this->missingTranscodeKeys($videoId, $s3Service);
        if ($missing !== []) {
            throw new RuntimeException(
                'Transcode artifacts missing on object storage: '.implode(', ', $missing)
            );
        }
    }
}
