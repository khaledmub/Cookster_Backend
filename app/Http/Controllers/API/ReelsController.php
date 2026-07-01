<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReelResource;
use App\Models\Video;
use App\Support\FeedSocialCache;
use App\Support\VideoFeedSort;
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

    private const GEO_SCOPE_NONE = 'none';

    private const GEO_SCOPE_CITY = 'city';

    private const GEO_SCOPE_LOCAL = 'local';

    private const GEO_SCOPE_COUNTRY = 'country';

    private const GEO_SCOPE_GLOBAL = 'global';

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
            'meta' => array_merge([
                'per_page' => $feedContext['per_page'],
                'has_more' => $payload['has_more'],
                'next_cursor' => $payload['next_cursor'],
                'geo_fallback' => $payload['geo_fallback'],
                'sort_by' => $feedContext['sort_by'],
            ], $this->nearMeMetaFields($feed, $geoContext, $payload)),
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
            && $cursor['created_at'] === null
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
                    $query->where(function ($q) {
                        $q->where('videos.transcode_status', 'ready')
                            ->orWhere('videos.is_image', 1);
                    });
                }
            } else {
                $query->where(function ($q) {
                    $q->where('videos.transcode_status', 'ready')
                        ->orWhere('videos.is_image', 1);
                });
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

        $useDistanceSort = $this->shouldSortNearMeByDistance($feed, $geoContext);

        if ($useDistanceSort) {
            $this->applyNearMeDistanceJoin($query, (float) $geoContext['latitude'], (float) $geoContext['longitude']);
        }

        if ($feed === self::FEED_NEAR_ME && empty($geoContext['geo_fallback'])) {
            $this->applyNearMeGeoFilters($query, $geoContext);
        }

        $anchorApplied = $this->applyAnchorOrCursor($query, $cursor, $feedContext);

        $sort = $feedContext['sort_by'];

        if (! $anchorApplied && $useDistanceSort && $cursor['near_me_distance'] !== null) {
            $this->applyNearMeDistanceCursor(
                $query,
                $cursor,
                $sort,
                (float) $geoContext['latitude'],
                (float) $geoContext['longitude']
            );
        } elseif (! $anchorApplied && $cursor['created_at'] !== null && $cursor['id'] !== null) {
            VideoFeedSort::applyKeysetCursor($query, $sort, $cursor['created_at'], $cursor['id']);
        } elseif (! $anchorApplied && $cursor['system_id'] !== null && $cursor['id'] !== null) {
            // Legacy cursor support (pre-sort_by deploy).
            $query->where(function ($q) use ($cursor) {
                $q->where('videos.system_id', '<', $cursor['system_id'])
                    ->orWhere(function ($q2) use ($cursor) {
                        $q2->where('videos.system_id', $cursor['system_id'])
                            ->where('videos.id', '<', $cursor['id']);
                    });
            });
        }

        $rows = $query
            ->when(
                $useDistanceSort,
                fn ($q) => $this->applyNearMeOrder(
                    $q,
                    $sort,
                    (float) $geoContext['latitude'],
                    (float) $geoContext['longitude']
                ),
                fn ($q) => VideoFeedSort::applyOrder($q, $sort)
            )
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
        if (($cursor['created_at'] !== null || $cursor['system_id'] !== null)
            || $feedContext['anchor_id'] === null
            || $feedContext['anchor_id'] === '') {
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

        $anchor = $anchorQuery->first(['id', 'system_id', 'created_at']);

        if ($anchor === null) {
            return false;
        }

        $sort = $feedContext['sort_by'];
        $createdAt = $anchor->created_at?->toDateTimeString() ?? (string) $anchor->created_at;

        if ($sort === VideoFeedSort::OLDEST) {
            $query->where(function ($q) use ($createdAt, $anchor) {
                $q->where('videos.created_at', '>', $createdAt)
                    ->orWhere(function ($q2) use ($createdAt, $anchor) {
                        $q2->where('videos.created_at', $createdAt)
                            ->where('videos.id', '>=', $anchor->id);
                    });
            });
        } else {
            $query->where(function ($q) use ($createdAt, $anchor) {
                $q->where('videos.created_at', '<', $createdAt)
                    ->orWhere(function ($q2) use ($createdAt, $anchor) {
                        $q2->where('videos.created_at', $createdAt)
                            ->where('videos.id', '<=', $anchor->id);
                    });
            });
        }

        return true;
    }

    /**
     * @param  array{feed: string, user_id: ?string, video_type: ?int, per_page: int, anchor_id: ?string}  $feedContext
     */
    private function encodeNextCursor(string $feed, array $feedContext, array $geoContext, Video $last): string
    {
        $cursorData = [
            'feed' => $feed,
            'id' => $last->id,
            'created_at' => $last->created_at?->toDateTimeString() ?? (string) $last->created_at,
            'sort_by' => $feedContext['sort_by'],
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

        if (
            $feed === self::FEED_NEAR_ME
            && isset($last->near_me_distance)
            && $last->near_me_distance !== null
        ) {
            $cursorData['near_me_distance'] = (float) $last->near_me_distance;
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
        $anchorId = $cursor['created_at'] === null
            && $cursor['system_id'] === null
            && $request->filled('anchor_id')
            ? (string) $request->input('anchor_id')
            : null;

        return [
            'feed' => $feed,
            'user_id' => $userId,
            'video_type' => $videoType,
            'per_page' => $perPage,
            'anchor_id' => $anchorId,
            'sort_by' => VideoFeedSort::fromRequest($request, $cursor),
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
     * @return array{
     *     cities_ids: array<int|string>,
     *     city: int,
     *     country_id: int,
     *     geo_scope: string,
     *     geo_radius_km: ?float,
     *     geo_fallback: bool,
     *     latitude: ?float,
     *     longitude: ?float,
     *     hash: string
     * }
     */
    private function resolveGeoContext(Request $request, array $cursor, string $feed): array
    {
        $empty = $this->emptyGeoContext();

        if ($feed !== self::FEED_NEAR_ME) {
            return $empty;
        }

        if (! empty($cursor['geo_fallback'])) {
            return array_merge($empty, [
                'geo_fallback' => true,
                'hash' => 'fallback',
            ]);
        }

        $lat = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $lng = $request->filled('longitude') ? (float) $request->input('longitude') : null;

        if ($request->filled('city')) {
            $manualCity = (int) $request->input('city');
            $citiesIds = FeedSocialCache::cityGroupIds($manualCity);
            $countryId = (int) (DB::table('cities')->where('id', $manualCity)->value('country_id') ?? 0);

            return [
                'cities_ids' => $citiesIds,
                'city' => $manualCity,
                'country_id' => $countryId,
                'geo_scope' => self::GEO_SCOPE_CITY,
                'geo_radius_km' => null,
                'geo_fallback' => false,
                'latitude' => $lat,
                'longitude' => $lng,
                'hash' => sha1('city:'.$manualCity.':'.implode(',', $citiesIds)),
            ];
        }

        if ($lat !== null && $lng !== null) {
            $scope = $this->parseGeoScope($request);

            if ($scope === self::GEO_SCOPE_LOCAL) {
                $radiusKm = $this->parseRadiusKm($request);
                $citiesIds = FeedSocialCache::localCityIds($lat, $lng, $radiusKm);
                $city = FeedSocialCache::nearestCityId($lat, $lng);

                return [
                    'cities_ids' => $citiesIds,
                    'city' => $city,
                    'country_id' => FeedSocialCache::countryIdFromCoords($lat, $lng),
                    'geo_scope' => self::GEO_SCOPE_LOCAL,
                    'geo_radius_km' => $radiusKm,
                    'geo_fallback' => false,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'hash' => sha1('local:'.round($lat, 3).':'.round($lng, 3).':'.$radiusKm.':'.implode(',', $citiesIds)),
                ];
            }

            if ($scope === self::GEO_SCOPE_GLOBAL) {
                return [
                    'cities_ids' => [],
                    'city' => FeedSocialCache::nearestCityId($lat, $lng),
                    'country_id' => 0,
                    'geo_scope' => self::GEO_SCOPE_GLOBAL,
                    'geo_radius_km' => null,
                    'geo_fallback' => false,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'hash' => sha1('global:'.round($lat, 3).':'.round($lng, 3)),
                ];
            }

            $countryId = $request->filled('country')
                ? (int) $request->input('country')
                : FeedSocialCache::countryIdFromCoords($lat, $lng);

            return [
                'cities_ids' => [],
                'city' => FeedSocialCache::nearestCityId($lat, $lng),
                'country_id' => $countryId,
                'geo_scope' => self::GEO_SCOPE_COUNTRY,
                'geo_radius_km' => null,
                'geo_fallback' => false,
                'latitude' => $lat,
                'longitude' => $lng,
                'hash' => sha1('country:'.$countryId.':'.round($lat, 3).':'.round($lng, 3)),
            ];
        }

        return $empty;
    }

    /**
     * @return array{
     *     cities_ids: array<int|string>,
     *     city: int,
     *     country_id: int,
     *     geo_scope: string,
     *     geo_radius_km: ?float,
     *     geo_fallback: bool,
     *     latitude: ?float,
     *     longitude: ?float,
     *     hash: string
     * }
     */
    private function emptyGeoContext(): array
    {
        return [
            'cities_ids' => [],
            'city' => 0,
            'country_id' => 0,
            'geo_scope' => self::GEO_SCOPE_NONE,
            'geo_radius_km' => null,
            'geo_fallback' => false,
            'latitude' => null,
            'longitude' => null,
            'hash' => 'none',
        ];
    }

    private function parseGeoScope(Request $request): string
    {
        $scope = strtolower((string) $request->input('scope', self::GEO_SCOPE_COUNTRY));

        return in_array($scope, [self::GEO_SCOPE_LOCAL, self::GEO_SCOPE_COUNTRY, self::GEO_SCOPE_GLOBAL], true)
            ? $scope
            : self::GEO_SCOPE_COUNTRY;
    }

    private function parseRadiusKm(Request $request): float
    {
        if ($request->filled('radius_km')) {
            return max(1.0, (float) $request->input('radius_km'));
        }

        if ($request->filled('radius')) {
            return max(1.0, (float) $request->input('radius'));
        }

        return 50.0;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Video>  $query
     * @param  array{
     *     cities_ids: array<int|string>,
     *     city: int,
     *     country_id: int,
     *     geo_scope: string,
     *     geo_radius_km: ?float,
     *     geo_fallback: bool,
     *     latitude: ?float,
     *     longitude: ?float,
     *     hash: string
     * }  $geoContext
     */
    private function applyNearMeGeoFilters($query, array $geoContext): void
    {
        if ($geoContext['geo_scope'] === self::GEO_SCOPE_CITY || $geoContext['geo_scope'] === self::GEO_SCOPE_LOCAL) {
            if (! empty($geoContext['cities_ids'])) {
                $query->whereIn('videos.city', $geoContext['cities_ids']);
            }

            return;
        }

        if ($geoContext['geo_scope'] === self::GEO_SCOPE_COUNTRY && $geoContext['country_id'] > 0) {
            $query->where('videos.country', $geoContext['country_id']);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Video>  $query
     */
    private function applyNearMeDistanceJoin($query, float $lat, float $lng): void
    {
        $query->leftJoin('cities as near_me_city', 'near_me_city.id', '=', 'videos.city');
        $query->addSelect(DB::raw($this->nearMeDistanceExpression($lat, $lng).' AS near_me_distance'));
    }

    private function nearMeDistanceExpression(float $lat, float $lng): string
    {
        $distanceSql = FeedSocialCache::haversineDistanceSql(
            $lat,
            $lng,
            'COALESCE(near_me_city.latitude, 0)',
            'COALESCE(near_me_city.longitude, 0)'
        );

        return "CASE WHEN near_me_city.latitude IS NULL THEN 99999 ELSE {$distanceSql} END";
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Video>  $query
     */
    private function applyNearMeOrder($query, string $sort, float $lat, float $lng): void
    {
        $query->orderByRaw($this->nearMeDistanceExpression($lat, $lng));
        VideoFeedSort::applyOrder($query, $sort);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Video>  $query
     */
    private function applyNearMeDistanceCursor($query, array $cursor, string $sort, float $lat, float $lng): void
    {
        $distance = $cursor['near_me_distance'];
        $createdAt = $cursor['created_at'];
        $id = $cursor['id'];

        if ($distance === null || $createdAt === null || $id === null) {
            return;
        }

        $distanceExpr = $this->nearMeDistanceExpression($lat, $lng);

        $query->where(function ($outer) use ($distanceExpr, $distance, $createdAt, $id, $sort) {
            $outer->whereRaw("{$distanceExpr} > ?", [$distance])
                ->orWhere(function ($inner) use ($distanceExpr, $distance, $createdAt, $id, $sort) {
                    $inner->whereRaw("{$distanceExpr} = ?", [$distance]);
                    VideoFeedSort::applyKeysetCursor($inner, $sort, $createdAt, $id);
                });
        });
    }

    /**
     * @param  array{
     *     cities_ids: array<int|string>,
     *     city: int,
     *     country_id: int,
     *     geo_scope: string,
     *     geo_radius_km: ?float,
     *     geo_fallback: bool,
     *     latitude: ?float,
     *     longitude: ?float,
     *     hash: string
     * }  $geoContext
     */
    private function shouldSortNearMeByDistance(string $feed, array $geoContext): bool
    {
        if ($feed !== self::FEED_NEAR_ME || ! empty($geoContext['geo_fallback'])) {
            return false;
        }

        if ($geoContext['latitude'] === null || $geoContext['longitude'] === null) {
            return false;
        }

        return in_array($geoContext['geo_scope'], [
            self::GEO_SCOPE_CITY,
            self::GEO_SCOPE_LOCAL,
            self::GEO_SCOPE_COUNTRY,
            self::GEO_SCOPE_GLOBAL,
        ], true);
    }

    /**
     * @param  array{geo_fallback: bool}  $payload
     * @return array<string, mixed>
     */
    private function nearMeMetaFields(string $feed, array $geoContext, array $payload): array
    {
        if ($feed !== self::FEED_NEAR_ME) {
            return [];
        }

        if (! empty($payload['geo_fallback'])) {
            return [
                'geo_scope' => self::GEO_SCOPE_NONE,
                'geo_radius_km' => null,
            ];
        }

        return [
            'geo_scope' => $geoContext['geo_scope'],
            'geo_radius_km' => $geoContext['geo_radius_km'],
        ];
    }

    /**
     * @return array{
     *     cache_key: string,
     *     system_id: ?int,
     *     created_at: ?string,
     *     id: ?string,
     *     near_me_distance: ?float,
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
            'created_at' => null,
            'id' => null,
            'near_me_distance' => null,
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

        if (! is_array($data) || ! isset($data['id'])) {
            return $empty;
        }

        $createdAt = isset($data['created_at']) ? (string) $data['created_at'] : null;
        $systemId = isset($data['system_id']) ? (int) $data['system_id'] : null;

        if ($createdAt === null && $systemId === null) {
            return $empty;
        }

        return [
            'cache_key' => sha1((string) $rawCursor),
            'system_id' => $systemId,
            'created_at' => $createdAt,
            'id' => (string) $data['id'],
            'near_me_distance' => isset($data['near_me_distance']) ? (float) $data['near_me_distance'] : null,
            'sort_by' => VideoFeedSort::resolve($data['sort_by'] ?? null),
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

        return 'reels_feed_'.$feed.'_'.$viewerPart.'_b_'.$blockedHash.$geoPart.$followingPart.$userPart.'_s_'.$feedContext['sort_by'].'_'.$cursorKey;
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
