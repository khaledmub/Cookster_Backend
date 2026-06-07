<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReelResource;
use App\Support\FeedSocialCache;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReelsController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request): JsonResponse
    {
        $cursor = $this->normalizeCursor($request->input('cursor'));
        $viewer = Auth::guard('sanctum')->user();
        $cacheKey = $this->feedCacheKey($cursor['cache_key'], $viewer);

        $payload = $this->rememberFeedPage($cacheKey, fn () => $this->fetchReelsPage($cursor, $request));

        return response()->json([
            'status' => true,
            'data' => ReelResource::collection($payload['items'])->resolve(),
            'meta' => [
                'per_page' => self::PER_PAGE,
                'has_more' => $payload['has_more'],
                'next_cursor' => $payload['next_cursor'],
            ],
        ]);
    }

    /**
     * @return array{items: \Illuminate\Support\Collection<int, Video>, has_more: bool, next_cursor: ?string}
     */
    private function fetchReelsPage(array $cursor, Request $request): array
    {
        $viewer = Auth::guard('sanctum')->user();

        $query = Video::query()
            ->select('videos.*')
            ->with(['user:id,name,image'])
            ->withCount([
                'comments as comments_count' => fn ($q) => $q->where('status', 1),
                'saves as likes_count' => fn ($q) => $q->where('status', 1),
            ])
            ->where('videos.status', 1)
            ->where('videos.is_soft_delete', 0)
            ->whereNotNull('videos.video')
            ->where('videos.video', '!=', '')
            ->whereIn('videos.publish_type', [1, 2]);

        if ($viewer) {
            $blockedIds = FeedSocialCache::blockedUserIds($viewer->id);

            if (! empty($blockedIds)) {
                $query->whereNotIn('videos.front_user_id', $blockedIds);
            }
        }

        if ($cursor['system_id'] !== null && $cursor['id'] !== null) {
            $query->where(function ($q) use ($cursor) {
                $q->where('videos.system_id', '<', $cursor['system_id'])
                    ->orWhere(function ($q2) use ($cursor) {
                        $q2->where('videos.system_id', $cursor['system_id'])
                            ->where('videos.id', '<', $cursor['id']);
                    });
            });
        }

        $rows = $query
            ->orderByDesc('videos.system_id')
            ->orderByDesc('videos.id')
            ->limit(self::PER_PAGE + 1)
            ->get();

        $hasMore = $rows->count() > self::PER_PAGE;
        $items = $rows->take(self::PER_PAGE)->values();

        $nextCursor = null;
        if ($hasMore && $items->isNotEmpty()) {
            $last = $items->last();
            $nextCursor = base64_encode(json_encode([
                'system_id' => $last->system_id,
                'id' => $last->id,
            ], JSON_THROW_ON_ERROR));
        }

        return [
            'items' => $items,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
        ];
    }

    /**
     * @return array{cache_key: string, system_id: ?int, id: ?string}
     */
    private function normalizeCursor(mixed $rawCursor): array
    {
        if ($rawCursor === null || $rawCursor === '') {
            return [
                'cache_key' => '_start',
                'system_id' => null,
                'id' => null,
            ];
        }

        $decoded = base64_decode((string) $rawCursor, true);
        if ($decoded === false) {
            return [
                'cache_key' => '_start',
                'system_id' => null,
                'id' => null,
            ];
        }

        try {
            $data = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [
                'cache_key' => '_start',
                'system_id' => null,
                'id' => null,
            ];
        }

        if (! is_array($data) || ! isset($data['system_id'], $data['id'])) {
            return [
                'cache_key' => '_start',
                'system_id' => null,
                'id' => null,
            ];
        }

        return [
            'cache_key' => sha1((string) $rawCursor),
            'system_id' => (int) $data['system_id'],
            'id' => (string) $data['id'],
        ];
    }

    private function feedCacheKey(string $cursorKey, mixed $viewer): string
    {
        if ($viewer === null) {
            return 'reels_feed_guest_'.$cursorKey;
        }

        $blockedHash = sha1(implode(',', FeedSocialCache::blockedUserIds($viewer->id)));

        return 'reels_feed_u_'.$viewer->id.'_b_'.$blockedHash.'_'.$cursorKey;
    }

    /**
     * @template T
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function rememberFeedPage(string $cacheKey, \Closure $callback): mixed
    {
        try {
            return Cache::remember($cacheKey, 30, $callback);
        } catch (\Throwable) {
            return $callback();
        }
    }
}
