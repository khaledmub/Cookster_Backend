<?php

namespace App\Http\Resources;

use App\Models\Video;
use App\Services\CdnService;
use App\Services\VideoMediaService;
use App\Support\ApiTimestamp;
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
        $isImage = \App\Helpers\AppHelper::normalizeIsImage($this->resource);
        $isPhotoPost = $isImage === 1;
        $isHlsReady = $transcodeStatus === 'ready';
        $processingStatus = (string) ($this->processing_status ?? '');

        $pathImage = $this->image ? (string) $this->image : '';
        $pathVideo = $this->video ? (string) $this->video : '';

        $originalKey = $pathVideo !== ''
            ? (str_starts_with($pathVideo, 'videos/') ? $pathVideo : 'videos/'.ltrim($pathVideo, '/'))
            : null;

        $hlsKey = VideoMediaService::resolveHlsKey($this->hls_url ?? null, (string) $this->id, $isHlsReady);

        $cdn = app(CdnService::class);

        if ($isPhotoPost) {
            $fullImageUrl = VideoMediaService::resolvePhotoFullImageUrl(
                $pathImage !== '' ? $pathImage : null,
                $pathVideo !== '' ? $pathVideo : null,
            );
            $photoThumbUrl = VideoMediaService::resolvePhotoThumbnailUrl(
                $pathImage !== '' ? $pathImage : null,
            );
            $videoUrl = $fullImageUrl;
            $coverImageUrl = $fullImageUrl;
            $thumbnailUrl = $photoThumbUrl ?? $fullImageUrl;
            $hlsPlaylistUrl = null;
            $videoSources = ['url_360' => null, 'url_720' => null, 'url_1080' => null];
            $videoUrlDirect = $pathImage !== ''
                ? $cdn->directUrlForPath(str_starts_with($pathImage, 'videos/') ? $pathImage : 'videos/'.$pathImage)
                : null;
            $hlsUrlDirect = null;
            $playbackReady = $fullImageUrl !== null;
        } else {
            $videoSources = VideoMediaService::videoSources((string) $this->id, $isHlsReady);
            $hlsPlaylistUrl = $isHlsReady && $hlsKey ? CdnUrl::forPath($hlsKey) : null;
            $thumbnailUrl = VideoMediaService::resolvePosterUrl(
                (string) $this->id,
                $pathImage !== '' ? $pathImage : null,
                $processingStatus !== '' ? $processingStatus : null,
                $transcodeStatus,
            );
            $coverImageUrl = VideoMediaService::resolveCoverImageUrl($pathImage !== '' ? $pathImage : null);
            $legacyVideoUrl = $isHlsReady && $originalKey ? CdnUrl::forPath($originalKey) : null;

            $row = (object) ['video_url' => $legacyVideoUrl];
            VideoMediaService::applyVideoPlaybackUrls($row, $videoSources, false, $isHlsReady, $legacyVideoUrl);
            $videoUrl = $row->video_url;

            $videoUrlDirect = null;
            if ($videoUrl !== null && $isHlsReady) {
                foreach ([1080, 720, 360] as $height) {
                    if (($videoSources['url_'.$height] ?? null) === $videoUrl) {
                        $videoUrlDirect = $cdn->directUrlForPath(VideoMediaService::mp4Key((string) $this->id, $height));
                        break;
                    }
                }
                $videoUrlDirect ??= ($originalKey ? $cdn->directUrlForPath($originalKey) : null);
            }

            $hlsUrlDirect = $isHlsReady && $hlsKey ? $cdn->directUrlForPath($hlsKey) : null;
            $playbackReady = $isHlsReady && (
                VideoMediaService::pickBestLadderMp4Url($videoSources) !== null
                || $hlsPlaylistUrl !== null
            );
        }

        return [
            'id' => $this->id,
            'created_at' => ApiTimestamp::format($this->created_at),
            'updated_at' => ApiTimestamp::format($this->updated_at),
            'is_image' => $isImage,
            'title' => $this->title,
            'description' => $this->description,
            'tags' => $this->tags,
            'menu' => $this->menu,
            'publish_type' => (int) ($this->publish_type ?? 0),
            'video_type' => (int) ($this->video_type ?? 0),
            'video' => $videoUrl,
            'video_url' => $videoUrl,
            'video_url_direct' => $videoUrlDirect,
            'hls_url' => $hlsPlaylistUrl,
            'hls_playlist_url' => $hlsPlaylistUrl,
            'hls_url_direct' => $hlsUrlDirect,
            'video_sources' => $videoSources,
            'thumbnail' => $thumbnailUrl,
            'thumbnail_url' => $thumbnailUrl,
            'image' => $coverImageUrl,
            'image_url' => $coverImageUrl,
            'thumbnail_blur' => VideoMediaService::resolvePosterBlurUrl(
                (string) $this->id,
                $processingStatus !== '' ? $processingStatus : null,
                $transcodeStatus,
            ),
            'transcode_status' => $transcodeStatus,
            'processing_status' => $processingStatus !== '' ? $processingStatus : ($isHlsReady ? 'ready' : 'processing'),
            'playback_ready' => $playbackReady,
            'likes_count' => (int) ($this->likes_count ?? 0),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'user_name' => $this->user->user_name ?? null,
                'image' => \App\Helpers\AppHelper::userImageUrl($this->user->image ? (string) $this->user->image : null),
            ]),
        ];
    }
}
