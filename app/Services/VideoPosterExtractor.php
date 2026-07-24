<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class VideoPosterExtractor
{
    /**
     * Extract a sharp ~720w feed poster and blur placeholder from a video frame.
     *
     * Prefer a real decoded frame over a soft cover thumb so cold starts look
     * instant before the first video frame paints.
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

        // Skip t=0 black frames common on phone captures; snap even dims for WebP.
        $posterProcess = new Process([
            $ffmpeg,
            '-y',
            '-ss', '0.25',
            '-i', $sourcePath,
            '-frames:v', '1',
            '-vf', VideoEncodeFilters::posterScaleFilter(720),
            '-c:v', 'libwebp',
            '-quality', '88',
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
