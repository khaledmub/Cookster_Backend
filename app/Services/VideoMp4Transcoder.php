<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class VideoMp4Transcoder
{
    private const VARIANTS = [
        360 => ['height' => 360, 'video_kbps' => 600, 'audio_kbps' => 64],
        720 => ['height' => 720, 'video_kbps' => 2500, 'audio_kbps' => 128],
        1080 => ['height' => 1080, 'video_kbps' => 5000, 'audio_kbps' => 192],
    ];

    /**
     * @return array<int, string> Map of height => local file path
     */
    public function transcode(string $sourcePath, string $workDir): array
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException('Source video file not found: '.$sourcePath);
        }

        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Unable to create MP4 work directory: '.$workDir);
        }

        $ffmpeg = (string) config('ffmpeg.ffmpeg.binaries', '/usr/bin/ffmpeg');
        $timeout = (int) config('ffmpeg.timeout', 7200);
        $outputs = [];

        foreach (self::VARIANTS as $height => $variant) {
            $outputPath = $workDir.'/'.$height.'.mp4';

            $process = new Process([
                $ffmpeg,
                '-y',
                '-i', $sourcePath,
                '-vf', 'scale=-2:'.$variant['height'],
                '-c:v', 'libx264',
                '-b:v', (string) $variant['video_kbps'].'k',
                '-c:a', 'aac',
                '-b:a', (string) $variant['audio_kbps'].'k',
                '-movflags', '+faststart',
                $outputPath,
            ]);
            $process->setTimeout($timeout);
            $process->mustRun();

            if (! is_file($outputPath) || filesize($outputPath) === 0) {
                throw new RuntimeException('FFmpeg did not produce MP4 rendition: '.$outputPath);
            }

            $outputs[$height] = $outputPath;
        }

        return $outputs;
    }
}
