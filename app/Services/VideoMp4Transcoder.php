<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class VideoMp4Transcoder
{
    private const VARIANTS = [
        360 => ['height' => 360, 'video_kbps' => 600, 'audio_kbps' => 96],
        720 => ['height' => 720, 'video_kbps' => 2500, 'audio_kbps' => 128],
        1080 => ['height' => 1080, 'video_kbps' => 5000, 'audio_kbps' => 192],
    ];

    /**
     * @param  list<int>|null  $ladderHeights  Heights to encode; null = full ladder.
     * @return array<int, string> Map of height => local file path
     */
    public function transcode(string $sourcePath, string $workDir, ?array $ladderHeights = null): array
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException('Source video file not found: '.$sourcePath);
        }

        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Unable to create MP4 work directory: '.$workDir);
        }

        $heights = $ladderHeights ?? array_keys(self::VARIANTS);
        $ffmpeg = (string) config('ffmpeg.ffmpeg.binaries', '/usr/bin/ffmpeg');
        $timeout = (int) config('ffmpeg.timeout', 7200);
        $preset = (string) config('ffmpeg.preset', 'fast');
        $threads = (int) config('ffmpeg.threads', 0);
        $gop = max(1, (int) config('ffmpeg.gop_size', 48));
        $profile = (string) config('ffmpeg.video_profile', 'main');
        $outputs = [];

        foreach ($heights as $height) {
            if (! isset(self::VARIANTS[$height])) {
                continue;
            }

            $variant = self::VARIANTS[$height];
            $outputPath = $workDir.'/'.$height.'.mp4';

            $command = [
                $ffmpeg,
                '-y',
                '-i', $sourcePath,
                '-vf', 'scale=-2:'.$variant['height'],
                '-c:v', 'libx264',
                '-profile:v', $profile,
                '-preset', $preset,
                '-b:v', (string) $variant['video_kbps'].'k',
                '-g', (string) $gop,
                '-keyint_min', (string) $gop,
                '-sc_threshold', '0',
                '-c:a', 'aac',
                '-b:a', (string) $variant['audio_kbps'].'k',
                '-movflags', '+faststart',
            ];

            if ($threads > 0) {
                $command[] = '-threads';
                $command[] = (string) $threads;
            }

            $command[] = $outputPath;

            $process = new Process($command);
            $process->setTimeout($timeout);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            if (! is_file($outputPath) || filesize($outputPath) === 0) {
                throw new RuntimeException('FFmpeg did not produce MP4 rendition: '.$outputPath);
            }

            $outputs[$height] = $outputPath;
        }

        return $outputs;
    }
}
