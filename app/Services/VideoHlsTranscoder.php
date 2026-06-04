<?php

namespace App\Services;

use App\FFMpeg\Format\HlsX264;
use FFMpeg\FFMpeg;
use RuntimeException;

class VideoHlsTranscoder
{
    private const VARIANTS = [
        '360' => [
            'height' => 360,
            'video_kbps' => 600,
            'audio_kbps' => 64,
            'playlist' => 'video_360.m3u8',
            'segment' => 'video_360_%03d.ts',
        ],
        '720' => [
            'height' => 720,
            'video_kbps' => 2500,
            'audio_kbps' => 128,
            'playlist' => 'video_720.m3u8',
            'segment' => 'video_720_%03d.ts',
        ],
        '1080' => [
            'height' => 1080,
            'video_kbps' => 5000,
            'audio_kbps' => 192,
            'playlist' => 'video_1080.m3u8',
            'segment' => 'video_1080_%03d.ts',
        ],
    ];

    /**
     * Transcode a local MP4 into three HLS renditions plus a master playlist.
     *
     * @return array{work_dir: string, master_playlist: string}
     */
    public function transcode(string $sourcePath, string $workDir): array
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException('Source video file not found: '.$sourcePath);
        }

        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Unable to create HLS work directory: '.$workDir);
        }

        $ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => config('ffmpeg.ffmpeg.binaries'),
            'ffprobe.binaries' => config('ffmpeg.ffprobe.binaries'),
            'timeout' => config('ffmpeg.timeout'),
        ]);

        $video = $ffmpeg->open($sourcePath);
        $segmentSeconds = (int) config('ffmpeg.hls_segment_seconds', 6);

        $renditionMeta = [];

        foreach (self::VARIANTS as $label => $variant) {
            $playlistPath = $workDir.'/'.$variant['playlist'];
            $segmentPattern = $workDir.'/'.$variant['segment'];

            $format = new HlsX264('aac', 'libx264');
            $format->setPasses(1);
            $format->setKiloBitrate($variant['video_kbps']);
            $format->setAudioKiloBitrate($variant['audio_kbps']);
            $format->setAdditionalParameters([
                '-vf', 'scale=-2:'.$variant['height'],
                '-f', 'hls',
                '-hls_time', (string) $segmentSeconds,
                '-hls_list_size', '0',
                '-hls_segment_filename', $segmentPattern,
                '-hls_flags', 'independent_segments',
            ]);

            $video->save($format, $playlistPath);

            if (! is_file($playlistPath)) {
                throw new RuntimeException('FFmpeg did not produce playlist: '.$playlistPath);
            }

            $renditionMeta[] = [
                'label' => $label,
                'playlist' => $variant['playlist'],
                'bandwidth' => ($variant['video_kbps'] + $variant['audio_kbps']) * 1000,
                'height' => $variant['height'],
            ];
        }

        $masterPath = $workDir.'/master.m3u8';
        $this->writeMasterPlaylist($masterPath, $renditionMeta);

        return [
            'work_dir' => $workDir,
            'master_playlist' => $masterPath,
        ];
    }

    /**
     * @param  list<array{playlist: string, bandwidth: int, height: int}>  $renditions
     */
    private function writeMasterPlaylist(string $path, array $renditions): void
    {
        $lines = [
            '#EXTM3U',
            '#EXT-X-VERSION:3',
        ];

        foreach ($renditions as $rendition) {
            $width = (int) round($rendition['height'] * 16 / 9);
            $lines[] = '#EXT-X-STREAM-INF:BANDWIDTH='.$rendition['bandwidth'].',RESOLUTION='.$width.'x'.$rendition['height'];
            $lines[] = $rendition['playlist'];
        }

        if (file_put_contents($path, implode("\n", $lines)."\n") === false) {
            throw new RuntimeException('Unable to write master playlist: '.$path);
        }
    }
}
