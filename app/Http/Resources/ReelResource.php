<?php

namespace App\Http\Resources;

use App\Models\Video;
use App\Services\CdnService;
use App\Services\VideoMediaService;
use App\Support\CdnUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Video */
class ReelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $transcodeStatus = (string) ($this->transcode_status ?? 'pending');
        $isHlsReady = $transcodeStatus === 'ready';
        $processingStatus = (string) ($this->processing_status ?? '');

        $originalKey = $this->video
            ? (str_starts_with((string) $this->video, 'videos/') ? (string) $this->video : 'videos/'.ltrim((string) $this->video, '/'))
            : null;

        $hlsKey = VideoMediaService::resolveHlsKey($this->hls_url ?? null, (string) $this->id, $isHlsReady);

        $cdn = app(CdnService::class);
        $videoUrl = CdnUrl::forPath($originalKey);
        $hlsPlaylistUrl = $hlsKey ? CdnUrl::forPath($hlsKey) : null;
        $thumbnailUrl = VideoMediaService::resolvePosterUrl(
            (string) $this->id,
            $this->image ? (string) $this->image : null,
            $processingStatus !== '' ? $processingStatus : null,
        );
        $coverImageUrl = VideoMediaService::resolveCoverImageUrl($this->image ? (string) $this->image : null);

        return [
            'id' => $this->id,
            'video' => $videoUrl,
            'video_url' => $videoUrl,
            'video_url_direct' => $cdn->directUrlForPath($originalKey),
            'hls_url' => $hlsPlaylistUrl,
            'hls_playlist_url' => $hlsPlaylistUrl,
            'hls_url_direct' => $isHlsReady ? $cdn->directUrlForPath($hlsKey) : null,
            'video_sources' => VideoMediaService::videoSources((string) $this->id, $isHlsReady),
            'thumbnail' => $thumbnailUrl,
            'thumbnail_url' => $thumbnailUrl,
            'image' => $coverImageUrl,
            'image_url' => $coverImageUrl,
            'thumbnail_blur' => VideoMediaService::resolvePosterBlurUrl(
                (string) $this->id,
                $processingStatus !== '' ? $processingStatus : null,
            ),
            'transcode_status' => $transcodeStatus,
            'processing_status' => $processingStatus !== '' ? $processingStatus : ($isHlsReady ? 'ready' : 'processing'),
            'likes_count' => (int) ($this->likes_count ?? 0),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'image' => \App\Helpers\AppHelper::userImageUrl($this->user->image ? (string) $this->user->image : null),
            ]),
        ];
    }
}
