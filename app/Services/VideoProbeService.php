<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class VideoProbeService
{
    /**
     * Returns the encoded video height in pixels, or null when unknown.
     */
    public function probeVideoHeight(string $sourcePath): ?int
    {
        if (! is_file($sourcePath)) {
            return null;
        }

        $ffprobe = (string) config('ffmpeg.ffprobe.binaries', '/usr/bin/ffprobe');
        $process = new Process([
            $ffprobe,
            '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=height',
            '-of', 'csv=p=0',
            $sourcePath,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $height = (int) trim($process->getOutput());

        return $height > 0 ? $height : null;
    }

    /**
     * Ladder heights to encode (360, 720, 1080) capped by source resolution.
     *
     * @return list<int>
     */
    public function ladderHeightsForSource(?int $sourceHeight): array
    {
        $all = [360, 720, 1080];
        if ($sourceHeight === null || $sourceHeight <= 0) {
            return $all;
        }

        return array_values(array_filter(
            $all,
            static fn (int $h) => $sourceHeight >= (int) ($h * 0.85)
        ));
    }
}
