<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class VideoMp4Transcoder
{
    private const VARIANTS = [
        360 => ['height' => 360, 'video_kbps' => 600, 'audio_kbps' => 64],
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
        $preset = (string) config('ffmpeg.preset', 'veryfast');
        $threads = (int) config('ffmpeg.threads', 0);
        $outputs = [];
        $running = [];

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
                '-preset', $preset,
                '-b:v', (string) $variant['video_kbps'].'k',
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
            $process->start();
            $running[$height] = ['process' => $process, 'path' => $outputPath];
        }

        foreach ($running as $height => $meta) {
            $exitCode = $meta['process']->wait();

            if ($exitCode !== 0) {
                throw new ProcessFailedException($meta['process']);
            }

            if (! is_file($meta['path']) || filesize($meta['path']) === 0) {
                throw new RuntimeException('FFmpeg did not produce MP4 rendition: '.$meta['path']);
            }

            $outputs[$height] = $meta['path'];
        }

        return $outputs;
    }
}
