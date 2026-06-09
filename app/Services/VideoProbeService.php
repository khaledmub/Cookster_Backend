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
     * HLS ladder heights (360, 720, 1080) capped by source resolution and config max.
     *
     * @return list<int>
     */
    public function ladderHeightsForSource(?int $sourceHeight): array
    {
        return $this->filterHeightsForSource(
            $this->configuredHeights('max_ladder_height', [360, 720, 1080]),
            $sourceHeight,
        );
    }

    /**
     * MP4 fallback heights — typically 360 only; 720/1080 come from HLS.
     *
     * @return list<int>
     */
    public function mp4LadderHeightsForSource(?int $sourceHeight): array
    {
        $configured = config('ffmpeg.mp4_ladder_heights');
        $heights = is_array($configured) && $configured !== []
            ? array_map('intval', $configured)
            : [360];

        return $this->filterHeightsForSource($heights, $sourceHeight);
    }

    /**
     * @param  list<int>  $heights
     * @return list<int>
     */
    private function filterHeightsForSource(array $heights, ?int $sourceHeight): array
    {
        $heights = array_values(array_unique(array_filter($heights, static fn (int $h) => $h > 0)));

        if ($heights === []) {
            $heights = [360];
        }

        if ($sourceHeight === null || $sourceHeight <= 0) {
            return $heights;
        }

        return array_values(array_filter(
            $heights,
            static fn (int $h) => $sourceHeight >= (int) ($h * 0.85)
        ));
    }

    /**
     * @param  list<int>  $defaults
     * @return list<int>
     */
    private function configuredHeights(string $configKey, array $defaults): array
    {
        if ($configKey === 'max_ladder_height') {
            $max = (int) config('ffmpeg.max_ladder_height', 1080);

            return array_values(array_filter(
                $defaults,
                static fn (int $h) => $h <= $max
            ));
        }

        return $defaults;
    }
}
