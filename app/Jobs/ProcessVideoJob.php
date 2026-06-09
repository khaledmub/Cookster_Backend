<?php

namespace App\Jobs;

use App\Events\VideoTranscoded;
use App\Helpers\AppHelper;
use App\Services\S3Service;
use App\Services\VideoHlsTranscoder;
use App\Services\VideoMediaService;
use App\Services\VideoMediaVerifier;
use App\Services\VideoMp4Transcoder;
use App\Services\VideoPosterExtractor;
use App\Services\VideoProbeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout;

    public function __construct(
        public string $videoId,
        public string $videoFilename,
    ) {
        $this->onQueue('video-processing');
        $this->timeout = (int) config('ffmpeg.timeout', 7200);
    }

    public function handle(
        VideoHlsTranscoder $transcoder,
        VideoMp4Transcoder $mp4Transcoder,
        VideoMediaVerifier $mediaVerifier,
        VideoPosterExtractor $posterExtractor,
        VideoProbeService $probeService,
        S3Service $s3Service,
    ): void {
        if (! Schema::hasColumn('videos', 'transcode_status')) {
            return;
        }

        $video = DB::table('videos')->where('id', $this->videoId)->first();
        if (! $video || empty($video->video)) {
            return;
        }

        if (($video->transcode_status ?? 'pending') === 'ready') {
            return;
        }

        $lock = Cache::lock('process-video:'.$this->videoId, $this->timeout);
        if (! $lock->get()) {
            $this->release(120);

            return;
        }

        try {
            if ($this->tryMarkReadyFromExistingArtifacts($mediaVerifier, $s3Service)) {
                return;
            }

            $this->runTranscode($video, $transcoder, $mp4Transcoder, $mediaVerifier, $posterExtractor, $probeService, $s3Service);
        } finally {
            $lock->release();
        }
    }

    private function runTranscode(
        object $video,
        VideoHlsTranscoder $transcoder,
        VideoMp4Transcoder $mp4Transcoder,
        VideoMediaVerifier $mediaVerifier,
        VideoPosterExtractor $posterExtractor,
        VideoProbeService $probeService,
        S3Service $s3Service,
    ): void {
        $this->updateTranscodeStatus('pending');

        $workDir = storage_path('app/hls-transcode/'.$this->videoId);
        $mp4Dir = $workDir.'/mp4';
        $sourcePath = $workDir.'/source.mp4';

        try {
            $this->prepareWorkDirectory($workDir);
            $this->downloadSource($s3Service, (string) $video->video, $sourcePath);

            $sourceHeight = $probeService->probeVideoHeight($sourcePath);
            $hlsLadderHeights = $probeService->ladderHeightsForSource($sourceHeight);
            $mp4LadderHeights = $probeService->mp4LadderHeightsForSource($sourceHeight);

            $result = $transcoder->transcode($sourcePath, $workDir, $hlsLadderHeights);
            $s3Prefix = 'videos/'.$this->videoId.'/hls';

            $this->uploadHlsArtifacts($s3Prefix, $result['work_dir'], $s3Service);

            $mp4Outputs = $mp4Transcoder->transcode($sourcePath, $mp4Dir, $mp4LadderHeights);
            $this->uploadMp4Artifacts('videos/'.$this->videoId, $mp4Outputs, $s3Service);

            $this->maybeExtractPosterFromVideo($video, $sourcePath, $workDir, $posterExtractor, $s3Service);

            $mediaVerifier->assertTranscodeReady($this->videoId, $s3Service, $mp4LadderHeights);

            $hlsKey = VideoMediaService::hlsMasterKey($this->videoId);
            $this->updateTranscodeStatus('ready', $hlsKey);

            $hlsUrl = AppHelper::absoluteUrlForStoredObject(
                AppHelper::mediaPublicBaseUrl(),
                $hlsKey,
                'videos/'
            ) ?? $hlsKey;

            event(new VideoTranscoded($this->videoId, $hlsUrl, 'ready'));
        } catch (Throwable $e) {
            $this->handleTranscodeFailure($e);
        } finally {
            $this->cleanupDirectory($workDir);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ProcessVideoJob permanently failed', [
            'video_id' => $this->videoId,
            'attempts' => $this->attempts(),
            'message' => $exception->getMessage(),
        ]);

        $this->updateTranscodeStatus('failed');
    }

    private function tryMarkReadyFromExistingArtifacts(
        VideoMediaVerifier $mediaVerifier,
        S3Service $s3Service,
    ): bool {
        try {
            $mediaVerifier->assertTranscodeReady($this->videoId, $s3Service);
        } catch (Throwable) {
            return false;
        }

        $hlsKey = VideoMediaService::hlsMasterKey($this->videoId);
        $this->updateTranscodeStatus('ready', $hlsKey);

        $hlsUrl = AppHelper::absoluteUrlForStoredObject(
            AppHelper::mediaPublicBaseUrl(),
            $hlsKey,
            'videos/'
        ) ?? $hlsKey;

        event(new VideoTranscoded($this->videoId, $hlsUrl, 'ready'));

        Log::info('ProcessVideoJob skipped re-encode; artifacts already on storage', [
            'video_id' => $this->videoId,
        ]);

        return true;
    }

    private function handleTranscodeFailure(Throwable $e): void
    {
        Log::warning('ProcessVideoJob transcode attempt failed', [
            'video_id' => $this->videoId,
            'attempt' => $this->attempts(),
            'message' => $e->getMessage(),
        ]);

        if ($this->attempts() < $this->tries) {
            $this->updateTranscodeStatus('pending');
            $this->release(300);

            return;
        }

        $this->updateTranscodeStatus('failed');

        throw $e;
    }

    private function prepareWorkDirectory(string $workDir): void
    {
        if (is_dir($workDir)) {
            $this->cleanupDirectory($workDir);
        }

        if (! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Unable to create transcode directory: '.$workDir);
        }
    }

    private function downloadSource(S3Service $s3Service, string $filename, string $destination): void
    {
        $key = str_starts_with($filename, 'videos/') ? $filename : 'videos/'.$filename;

        if (! $s3Service->fileExists($key)) {
            throw new RuntimeException('Source video not found on object storage: '.$key);
        }

        $readStream = Storage::disk('s3')->readStream($key);
        if ($readStream === null) {
            throw new RuntimeException('Unable to open source video stream: '.$key);
        }

        $writeStream = fopen($destination, 'wb');
        if ($writeStream === false) {
            if (is_resource($readStream)) {
                fclose($readStream);
            }
            throw new RuntimeException('Unable to write source video to disk: '.$destination);
        }

        try {
            stream_copy_to_stream($readStream, $writeStream);
        } finally {
            if (is_resource($readStream)) {
                fclose($readStream);
            }
            if (is_resource($writeStream)) {
                fclose($writeStream);
            }
        }

        if (! is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException('Downloaded source video is empty: '.$key);
        }
    }

    /**
     * @param  array<int, string>  $localFiles  height => path
     */
    private function uploadMp4Artifacts(string $s3Prefix, array $localFiles, S3Service $s3Service): void
    {
        foreach ($localFiles as $height => $localPath) {
            $remoteKey = $s3Prefix.'/'.$height.'.mp4';
            $s3Service->storeFileFromPath($remoteKey, $localPath, [
                'mimetype' => 'video/mp4',
            ]);
        }
    }

    private function uploadHlsArtifacts(string $s3Prefix, string $localDir, S3Service $s3Service): void
    {
        foreach (scandir($localDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $localPath = $localDir.'/'.$entry;
            if (! is_file($localPath)) {
                continue;
            }

            if ($entry === 'source.mp4' || (! str_ends_with($entry, '.m3u8') && ! str_ends_with($entry, '.ts'))) {
                continue;
            }

            $remoteKey = $s3Prefix.'/'.$entry;
            $s3Service->storeFileFromPath($remoteKey, $localPath, [
                'mimetype' => $this->mimeForHlsFile($entry),
            ]);
        }
    }

    private function maybeExtractPosterFromVideo(
        object $video,
        string $sourcePath,
        string $workDir,
        VideoPosterExtractor $posterExtractor,
        S3Service $s3Service,
    ): void {
        if (! Schema::hasColumn('videos', 'processing_status')) {
            return;
        }

        $processingStatus = (string) ($video->processing_status ?? 'ready');
        $hasCoverImage = ! empty($video->image);

        if ($hasCoverImage || $processingStatus !== 'processing') {
            return;
        }

        $posterDir = $workDir.'/poster';
        $extracted = $posterExtractor->extract($sourcePath, $posterDir);

        $s3Service->storeFileFromPath(
            VideoMediaService::posterKey($this->videoId),
            $extracted['poster'],
            ['mimetype' => 'image/webp']
        );

        $s3Service->storeFileFromPath(
            VideoMediaService::posterBlurKey($this->videoId),
            $extracted['blur'],
            ['mimetype' => 'image/webp']
        );

        DB::table('videos')
            ->where('id', $this->videoId)
            ->update(['processing_status' => 'ready']);
    }

    private function mimeForHlsFile(string $filename): string
    {
        if (str_ends_with($filename, '.m3u8')) {
            return 'application/vnd.apple.mpegurl';
        }

        if (str_ends_with($filename, '.ts')) {
            return 'video/mp2t';
        }

        return 'application/octet-stream';
    }

    private function updateTranscodeStatus(string $status, ?string $hlsUrl = null): void
    {
        if (! Schema::hasColumn('videos', 'transcode_status')) {
            return;
        }

        $payload = ['transcode_status' => $status];

        if ($hlsUrl !== null && Schema::hasColumn('videos', 'hls_url')) {
            $payload['hls_url'] = $hlsUrl;
        }

        DB::table('videos')
            ->where('id', $this->videoId)
            ->update($payload);
    }

    private function cleanupDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir.'/'.$entry;
            if (is_dir($path)) {
                $this->cleanupDirectory($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        @rmdir($dir);
    }
}
