<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class VideoPosterExtractor
{
    /**
     * Extract a poster and blur placeholder from the first decodable video frame.
     *
     * @return array{poster: string, blur: string} Local file paths
     */
    public function extract(string $sourcePath, string $workDir): array
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException('Source video file not found: '.$sourcePath);
        }

        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Unable to create poster work directory: '.$workDir);
        }

        $ffmpeg = (string) config('ffmpeg.ffmpeg.binaries', '/usr/bin/ffmpeg');
        $timeout = (int) config('ffmpeg.timeout', 7200);

        $posterPath = $workDir.'/poster.webp';
        $blurPath = $workDir.'/poster_blur.webp';

        $posterProcess = new Process([
            $ffmpeg,
            '-y',
            '-ss', '0',
            '-i', $sourcePath,
            '-frames:v', '1',
            '-vf', 'scale=720:-2',
            '-c:v', 'libwebp',
            '-quality', '82',
            $posterPath,
        ]);
        $posterProcess->setTimeout(min($timeout, 120));
        $posterProcess->mustRun();

        if (! is_file($posterPath) || filesize($posterPath) === 0) {
            throw new RuntimeException('FFmpeg did not produce poster frame: '.$posterPath);
        }

        $blurProcess = new Process([
            $ffmpeg,
            '-y',
            '-i', $posterPath,
            '-vf', 'scale=24:24:force_original_aspect_ratio=increase,crop=24:24',
            '-c:v', 'libwebp',
            '-quality', '60',
            $blurPath,
        ]);
        $blurProcess->setTimeout(60);
        $blurProcess->mustRun();

        if (! is_file($blurPath) || filesize($blurPath) === 0) {
            throw new RuntimeException('FFmpeg did not produce blur poster: '.$blurPath);
        }

        return [
            'poster' => $posterPath,
            'blur' => $blurPath,
        ];
    }
}
