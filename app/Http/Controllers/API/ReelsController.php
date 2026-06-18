<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReelResource;
use App\Models\Video;
use App\Support\FeedSocialCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReelsController extends Controller
{
    private const PER_PAGE_DEFAULT = 10;

    private const PER_PAGE_MAX = 30;

    private const FEED_GENERAL = 'general';

    private const FEED_NEAR_ME = 'near_me';

    private const FEED_FOLLOWING = 'following';

    private const FEED_USER = 'user';

    public function index(Request $request): JsonResponse
    {
        $cursor = $this->normalizeCursor($request->input('cursor'));
        $feedContext = $this->resolveFeedContext($request, $cursor);
        $feed = $feedContext['feed'];
        $viewer = Auth::guard('sanctum')->user();

        if ($feed === self::FEED_FOLLOWING && $viewer === null) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if ($feed === self::FEED_USER) {
            if ($feedContext['user_id'] === null || $feedContext['user_id'] === '') {
                return response()->json([
                    'status' => false,
                    'message' => 'user_id is required for feed=user',
                ], 400);
            }

            $ownerExists = DB::table('front_users')
                ->where('id', $feedContext['user_id'])
                ->where('is_soft_delete', 0)
                ->exists();

            if (! $ownerExists) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found',
                ], 404);
            }
        }

        $geoContext = $this->resolveGeoContext($request, $cursor, $feed);
        $cacheKey = $this->feedCacheKey($feed, $cursor['cache_key'], $viewer, $geoContext, $feedContext);

        $payload = $this->rememberFeedPage(
            $cacheKey,
            fn () => $this->fetchReelsPage($feed, $cursor, $viewer, $geoContext, $feedContext)
        );

        return response()->json([
            'status' => true,
            'data' => ReelResource::collection($payload['items'])->resolve(),
            'meta' => [
                'per_page' => $feedContext['per_page'],
                'has_more' => $payload['has_more'],
                'next_cursor' => $payload['next_cursor'],
                'geo_fallback' => $payload['geo_fallback'],
            ],
        ]);
    }

    /**
     * @param  array{feed: string, user_id: ?string, video_type: ?int, per_page: int, anchor_id: ?string}  $feedContext
     * @return array{items: Collection<int, Video>, has_more: bool, next_cursor: ?string, geo_fallback: bool}
     */
    private function fetchReelsPage(string $feed, array $cursor, mixed $viewer, array $geoContext, array $feedContext): array
    {
        $geoFallback = $feed === self::FEED_USER
            ? false
            : (bool) ($geoContext['geo_fallback'] ?? false);

        $result = $this->executeReelsQuery($feed, $cursor, $viewer, $geoContext, $feedContext);

        if (
            $feed === self::FEED_NEAR_ME
            && $result['items']->isEmpty()
            && ! $geoFallback
            && $cursor['system_id'] === null
        ) {
            $fallbackGeo = [
                'cities_ids' => [],
                'city' => 0,
                'geo_fallback' => true,
                'hash' => 'fallback',
            ];

            $result = $this->executeReelsQuery($feed, $cursor, $viewer, $fallbackGeo, $feedContext);
            $result['geo_fallback'] = true;

            return $result;
        }

        $result['geo_fallback'] = $geoFallback;

        return $result;
    }

    /**
     * @param  array{feed: string, user_id: ?string, video_type: ?int, per_page: int, anchor_id: ?string}  $feedContext
     * @return array{items: Collection<int, Video>, has_more: bool, next_cursor: ?string}
     */
    private function executeReelsQuery(string $feed, array $cursor, mixed $viewer, array $geoContext, array $feedContext): array
    {
        $perPage = $feedContext['per_page'];

        $query = Video::query()
            ->select('videos.*')
            ->with(['user:id,name,user_name,image'])
            ->withCount([
                'comments as comments_count' => fn ($q) => $q->where('status', 1),
                'saves as likes_count' => fn ($q) => $q->where('status', 1),
            ])
            ->where('videos.status', 1)
            ->where('videos.is_soft_delete', 0)
            ->whereNotNull('videos.video')
            ->where('videos.video', '!=', '')
            ->whereIn('videos.publish_type', [1, 2]);

        if (\Illuminate\Support\Facades\Schema::hasColumn('videos', 'transcode_status')) {
            if ($feed === self::FEED_USER) {
                // Owner may see processing tiles (poster only); visitors get ready videos only.
                $viewerId = $viewer !== null ? (string) $viewer->id : null;
                $profileUserId = $feedContext['user_id'] ?? null;
                if ($viewerId === null || $profileUserId === null || $viewerId !== (string) $profileUserId) {
                    $query->where('videos.transcode_status', 'ready');
                }
            } else {
                $query->where('videos.transcode_status', 'ready');
            }
        }

        if ($viewer) {
            $blockedIds = FeedSocialCache::blockedUserIds($viewer->id);

            if (! empty($blockedIds)) {
                $query->whereNotIn('videos.front_user_id', $blockedIds);
            }
        }

        if ($feed === self::FEED_FOLLOWING) {
            $followingIds = FeedSocialCache::followingIds($viewer->id);

            if (empty($followingIds)) {
                return [
                    'items' => collect(),
                    'has_more' => false,
                    'next_cursor' => null,
                ];
            }

            $query->whereIn('videos.front_user_id', $followingIds);
        }

        if ($feed === self::FEED_USER) {
            $this->applyUserFeedFilters($query, $feedContext['user_id'], $feedContext['video_type'], $viewer);
        }

        if (
            $feed === self::FEED_NEAR_ME
            && ! empty($geoContext['cities_ids'])
            && empty($geoContext['geo_fallback'])
        ) {
            $query->whereIn('videos.city', $geoContext['cities_ids']);
        }

        $anchorApplied = $this->applyAnchorOrCursor($query, $cursor, $feedContext);

        if (! $anchorApplied && $cursor['system_id'] !== null && $cursor['id'] !== null) {
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
            ->limit($perPage + 1)
            ->get();

        $hasMore = $rows->count() > $perPage;
        $items = $rows->take($perPage)->values();

        $nextCursor = null;
        if ($hasMore && $items->isNotEmpty()) {
            $last = $items->last();
            $nextCursor = $this->encodeNextCursor($feed, $feedContext, $geoContext, $last);
        }

        return [
            'items' => $items,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Video>  $query
     */
    private function applyUserFeedFilters($query, string $userId, ?int $videoType, mixed $viewer): void
    {
        $query->where('videos.front_user_id', $userId);

        if ($videoType !== null) {
            $query->where('videos.video_type', $videoType);
        }

        $isOwnProfile = $viewer !== null && (string) $viewer->id === (string) $userId;

        if (! $isOwnProfile) {
            $query->join('front_users as profile_owner', 'profile_owner.id', '=', 'videos.front_user_id')
                ->leftJoin('subscription_history as sh', 'sh.id', '=', 'profile_owner.current_subscription_id')
                ->where(function ($q) {
                    $q->whereDate('sh.end_date', '>=', now()->toDateString())
                        ->orWhereNull('sh.end_date');
                });
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Video>  $query
     * @param  array{feed: string, user_id: ?string, video_type: ?int, per_page: int, anchor_id: ?string}  $feedContext
     */
    private function applyAnchorOrCursor($query, array $cursor, array $feedContext): bool
    {
        if ($cursor['system_id'] !== null || $feedContext['anchor_id'] === null || $feedContext['anchor_id'] === '') {
            return false;
        }

        $anchorQuery = Video::query()
            ->where('id', $feedContext['anchor_id'])
            ->where('status', 1)
            ->where('is_soft_delete', 0)
            ->whereNotNull('video')
            ->where('video', '!=', '');

        if ($feedContext['feed'] === self::FEED_USER && $feedContext['user_id'] !== null) {
            $anchorQuery->where('front_user_id', $feedContext['user_id']);
        }

        $anchor = $anchorQuery->first(['id', 'system_id']);

        if ($anchor === null) {
            return false;
        }

        $query->where(function ($q) use ($anchor) {
            $q->where('videos.system_id', '<', $anchor->system_id)
                ->orWhere(function ($q2) use ($anchor) {
                    $q2->where('videos.system_id', $anchor->system_id)
                        ->where('videos.id', '<=', $anchor->id);
                });
        });

        return true;
    }

    /**
     * @param  array{feed: string, user_id: ?string, video_type: ?int, per_page: int, anchor_id: ?string}  $feedContext
     */
    private function encodeNextCursor(string $feed, array $feedContext, array $geoContext, Video $last): string
    {
        $cursorData = [
            'feed' => $feed,
            'system_id' => $last->system_id,
            'id' => $last->id,
        ];

        if ($feed === self::FEED_USER && $feedContext['user_id'] !== null) {
            $cursorData['user_id'] = $feedContext['user_id'];

            if ($feedContext['video_type'] !== null) {
                $cursorData['video_type'] = $feedContext['video_type'];
            }
        }

        if ($feed === self::FEED_NEAR_ME && ! empty($geoContext['geo_fallback'])) {
            $cursorData['geo_fallback'] = true;
        }

        return base64_encode(json_encode($cursorData, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{feed: string, user_id: ?string, video_type: ?int, per_page: int, anchor_id: ?string}
     */
    private function resolveFeedContext(Request $request, array $cursor): array
    {
        $feed = $cursor['feed'] ?? $this->normalizeFeed($request);
        $userId = $cursor['user_id'] ?? ($request->filled('user_id') ? (string) $request->input('user_id') : null);
        $videoType = $cursor['video_type'] ?? ($request->filled('video_type') ? (int) $request->input('video_type') : null);
        $perPage = min(
            max((int) ($request->input('per_page') ?: self::PER_PAGE_DEFAULT), 1),
            self::PER_PAGE_MAX
        );
        $anchorId = $cursor['system_id'] === null && $request->filled('anchor_id')
            ? (string) $request->input('anchor_id')
            : null;

        return [
            'feed' => $feed,
            'user_id' => $userId,
            'video_type' => $videoType,
            'per_page' => $perPage,
            'anchor_id' => $anchorId,
        ];
    }

    private function normalizeFeed(Request $request): string
    {
        $feed = strtolower((string) $request->input('feed', self::FEED_GENERAL));

        return in_array($feed, [self::FEED_GENERAL, self::FEED_NEAR_ME, self::FEED_FOLLOWING, self::FEED_USER], true)
            ? $feed
            : self::FEED_GENERAL;
    }

    /**
     * @return array{cities_ids: array<int|string>, city: int, geo_fallback: bool, hash: string}
     */
    private function resolveGeoContext(Request $request, array $cursor, string $feed): array
    {
        if ($feed !== self::FEED_NEAR_ME) {
            return [
                'cities_ids' => [],
                'city' => 0,
                'geo_fallback' => false,
                'hash' => 'none',
            ];
        }

        if (! empty($cursor['geo_fallback'])) {
            return [
                'cities_ids' => [],
                'city' => 0,
                'geo_fallback' => true,
                'hash' => 'fallback',
            ];
        }

        $manualCity = $request->filled('city') ? (int) $request->input('city') : null;

        if ($request->filled('latitude') && $request->filled('longitude')) {
            $nearMe = FeedSocialCache::nearMeCityIds(
                (float) $request->input('latitude'),
                (float) $request->input('longitude'),
                $manualCity
            );

            return [
                'cities_ids' => $nearMe['cities_ids'],
                'city' => $nearMe['city'],
                'geo_fallback' => false,
                'hash' => $nearMe['hash'],
            ];
        }

        if ($manualCity !== null) {
            $cityRow = DB::table('cities')->where('id', $manualCity)->first(['id', 'latitude', 'longitude']);

            if ($cityRow && $cityRow->latitude !== null && $cityRow->longitude !== null) {
                $nearMe = FeedSocialCache::nearMeCityIds(
                    (float) $cityRow->latitude,
                    (float) $cityRow->longitude,
                    $manualCity
                );

                return [
                    'cities_ids' => $nearMe['cities_ids'],
                    'city' => $nearMe['city'],
                    'geo_fallback' => false,
                    'hash' => $nearMe['hash'],
                ];
            }

            $citiesIds = FeedSocialCache::cityGroupIds($manualCity);

            return [
                'cities_ids' => $citiesIds,
                'city' => $manualCity,
                'geo_fallback' => false,
                'hash' => sha1('city:'.$manualCity.':'.implode(',', $citiesIds)),
            ];
        }

        return [
            'cities_ids' => [],
            'city' => 0,
            'geo_fallback' => false,
            'hash' => 'none',
        ];
    }

    /**
     * @return array{
     *     cache_key: string,
     *     system_id: ?int,
     *     id: ?string,
     *     geo_fallback: bool,
     *     feed: ?string,
     *     user_id: ?string,
     *     video_type: ?int
     * }
     */
    private function normalizeCursor(mixed $rawCursor): array
    {
        $empty = [
            'cache_key' => '_start',
            'system_id' => null,
            'id' => null,
            'geo_fallback' => false,
            'feed' => null,
            'user_id' => null,
            'video_type' => null,
        ];

        if ($rawCursor === null || $rawCursor === '') {
            return $empty;
        }

        $decoded = base64_decode((string) $rawCursor, true);
        if ($decoded === false) {
            return $empty;
        }

        try {
            $data = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $empty;
        }

        if (! is_array($data) || ! isset($data['system_id'], $data['id'])) {
            return $empty;
        }

        return [
            'cache_key' => sha1((string) $rawCursor),
            'system_id' => (int) $data['system_id'],
            'id' => (string) $data['id'],
            'geo_fallback' => ! empty($data['geo_fallback']),
            'feed' => isset($data['feed']) ? (string) $data['feed'] : null,
            'user_id' => isset($data['user_id']) ? (string) $data['user_id'] : null,
            'video_type' => isset($data['video_type']) ? (int) $data['video_type'] : null,
        ];
    }

    /**
     * @param  array{cities_ids: array<int|string>, city: int, geo_fallback: bool, hash: string}  $geoContext
     * @param  array{feed: string, user_id: ?string, video_type: ?int, per_page: int, anchor_id: ?string}  $feedContext
     */
    private function feedCacheKey(string $feed, string $cursorKey, mixed $viewer, array $geoContext, array $feedContext): string
    {
        $viewerPart = $viewer === null ? 'guest' : 'u_'.$viewer->id;
        $blockedHash = $viewer === null
            ? 'none'
            : sha1(implode(',', FeedSocialCache::blockedUserIds($viewer->id)));

        $geoPart = $feed === self::FEED_NEAR_ME ? '_g_'.$geoContext['hash'] : '';

        $followingPart = '';
        if ($feed === self::FEED_FOLLOWING && $viewer !== null) {
            $followingPart = '_f_'.sha1(implode(',', FeedSocialCache::followingIds($viewer->id)));
        }

        $userPart = '';
        if ($feed === self::FEED_USER && $feedContext['user_id'] !== null) {
            $typeHash = $feedContext['video_type'] !== null
                ? (string) $feedContext['video_type']
                : 'all';
            $userPart = '_user_'.$feedContext['user_id'].'_t_'.$typeHash;

            if ($feedContext['anchor_id'] !== null && $feedContext['anchor_id'] !== '') {
                $userPart .= '_a_'.$feedContext['anchor_id'];
            }
        }

        return 'reels_feed_'.$feed.'_'.$viewerPart.'_b_'.$blockedHash.$geoPart.$followingPart.$userPart.'_'.$cursorKey;
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
