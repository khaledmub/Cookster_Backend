<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class VideoMp4FastStart
{
    /**
     * True when the moov atom starts before the first mdat atom.
     */
    public function isFastStart(string $localPath): bool
    {
        if (! is_file($localPath)) {
            return false;
        }

        [$moov, $mdat] = $this->findTopLevelBoxOffsets($localPath);

        return $moov !== null && ($mdat === null || $moov < $mdat);
    }

    /**
     * Heuristic using head/tail slices to avoid full downloads when possible.
     *
     * @return bool|null true = fast-start, false = moov at end, null = need full file
     */
    public function appearsFastStartOnStorage(string $storageKey, ?Filesystem $disk = null): ?bool
    {
        $disk ??= Storage::disk('s3');

        if (! $disk->exists($storageKey)) {
            return null;
        }

        $size = $disk->size($storageKey);
        if ($size <= 0) {
            return null;
        }

        $sampleSize = (int) min(262144, $size);

        $head = $this->readRange($disk, $storageKey, 0, $sampleSize);
        [$moovHead, $mdatHead] = $this->findTopLevelBoxOffsetsInBuffer($head, 0);

        if ($moovHead !== null && ($mdatHead === null || $moovHead < $mdatHead)) {
            return true;
        }

        if ($size <= $sampleSize) {
            return $this->isFastStartFromBuffer($head, 0);
        }

        $tailStart = $size - $sampleSize;
        $tail = $this->readRange($disk, $storageKey, $tailStart, $sampleSize);
        [$moovTail] = $this->findTopLevelBoxOffsetsInBuffer($tail, $tailStart);

        if ($moovTail !== null && $mdatHead !== null && $moovTail > $mdatHead) {
            return false;
        }

        return null;
    }

    public function remuxFastStart(string $inputPath, string $outputPath): void
    {
        $ffmpeg = (string) config('ffmpeg.ffmpeg.binaries', '/usr/bin/ffmpeg');
        $timeout = (int) config('ffmpeg.timeout', 7200);

        $command = [
            $ffmpeg,
            '-y',
            '-i', $inputPath,
            '-c', 'copy',
            '-movflags', '+faststart',
            $outputPath,
        ];

        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if (! is_file($outputPath) || filesize($outputPath) === 0) {
            throw new RuntimeException('Fast-start remux produced empty output: '.$outputPath);
        }
    }

    /**
     * @return array{0: ?int, 1: ?int} moov offset, mdat offset
     */
    private function findTopLevelBoxOffsets(string $localPath): array
    {
        $handle = fopen($localPath, 'rb');
        if ($handle === false) {
            return [null, null];
        }

        $size = filesize($localPath);
        $moov = null;
        $mdat = null;
        $offset = 0;

        try {
            while ($offset + 8 <= $size) {
                fseek($handle, $offset);
                $header = fread($handle, 8);
                if ($header === false || strlen($header) < 8) {
                    break;
                }

                $boxSize = unpack('N', substr($header, 0, 4))[1];
                $boxType = substr($header, 4, 4);

                if ($boxType === 'moov' && $moov === null) {
                    $moov = $offset;
                }
                if ($boxType === 'mdat' && $mdat === null) {
                    $mdat = $offset;
                }

                if ($boxSize < 8) {
                    break;
                }

                $offset += $boxSize;
            }
        } finally {
            fclose($handle);
        }

        return [$moov, $mdat];
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function findTopLevelBoxOffsetsInBuffer(string $buffer, int $baseOffset): array
    {
        $moov = null;
        $mdat = null;
        $length = strlen($buffer);
        $offset = 0;

        while ($offset + 8 <= $length) {
            $boxSize = unpack('N', substr($buffer, $offset, 4))[1];
            $boxType = substr($buffer, $offset + 4, 4);

            if ($boxType === 'moov' && $moov === null) {
                $moov = $baseOffset + $offset;
            }
            if ($boxType === 'mdat' && $mdat === null) {
                $mdat = $baseOffset + $offset;
            }

            if ($boxSize < 8) {
                break;
            }

            $offset += $boxSize;
        }

        return [$moov, $mdat];
    }

    private function isFastStartFromBuffer(string $buffer, int $baseOffset): bool
    {
        [$moov, $mdat] = $this->findTopLevelBoxOffsetsInBuffer($buffer, $baseOffset);

        return $moov !== null && ($mdat === null || $moov < $mdat);
    }

    private function readRange(Filesystem $disk, string $key, int $offset, int $length): string
    {
        $stream = $disk->readStream($key);
        if ($stream === null) {
            throw new RuntimeException('Unable to read object stream: '.$key);
        }

        try {
            if ($offset > 0 && fseek($stream, $offset) !== 0) {
                throw new RuntimeException('Unable to seek object stream: '.$key);
            }

            $data = fread($stream, $length);

            return $data === false ? '' : $data;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
