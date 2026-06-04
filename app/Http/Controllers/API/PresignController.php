<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Services\CdnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PresignController extends Controller
{
    public function show(Request $request, string $videoId, CdnService $cdn): JsonResponse
    {
        $video = Video::query()->find($videoId);

        if (! $video) {
            return response()->json([
                'status' => false,
                'message' => 'Video not found',
            ], 404);
        }

        if ($video->front_user_id !== $request->user()->id) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        $storageKey = $video->storage_key;
        if ($storageKey === null) {
            return response()->json([
                'status' => false,
                'message' => 'Video has no media file',
            ], 404);
        }

        $ttl = (int) config('cdn.cloudfront.url_ttl', 86400);
        $cacheTtl = (int) config('cdn.presign_cache_ttl', 82800);
        $cacheKey = 'presign_'.$videoId;

        $url = $this->redisCache()->remember(
            $cacheKey,
            $cacheTtl,
            fn () => $cdn->generateSignedUrl($storageKey, $ttl)
        );

        return response()->json([
            'status' => true,
            'video_id' => $videoId,
            'url' => $url,
            'expires_in' => $ttl,
            'signed_with' => $cdn->isCloudFrontSigningEnabled() ? 'cloudfront' : 'object_storage',
        ]);
    }

    private function redisCache(): \Illuminate\Contracts\Cache\Repository
    {
        try {
            return Cache::store('redis');
        } catch (\Throwable) {
            return Cache::store();
        }
    }
}
