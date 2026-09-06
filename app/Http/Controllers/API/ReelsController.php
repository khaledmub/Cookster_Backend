<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReelResource;
use App\Models\Video;
use App\Support\FeedSocialCache;
use App\Support\ReelViewTracker;
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

    private const PIN_MAX_AGE_HOURS = 24;

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
        $payload = $this->loadReelsPage($feed, $cursor, $viewer, $geoContext, $feedContext);

        return response()->json([
            'status' => true,
            'data' => ReelResource::collection($payload['items'])->resolve(),
            'meta' => array_merge([
                'per_page' => $feedContext['per_page'],
                'has_more' => $payload['has_more'],
                'next_cursor' => $payload['next_cursor'],
                'geo_fallback' => $payload['geo_fallback'],
                'sort_by' => $feedContext['sort_by'],
            ], $this->nearMeMetaFields($feed, $geoContext, $payload), $this->unseenFirstMeta($feedContext, $payload), [
                'pinned_video_id' => $payload['pinned_video_id'] ?? null,
            ]),
        ]);
    }

    public function storeView(Request $request, string $id): JsonResponse
    {
        return $this->recordReelViews($request, [$id]);
    }

    public function storeViews(Request $request): JsonResponse
    {
        $ids = $request->input('video_ids', $request->input('ids', []));
        if (! is_array($ids)) {
            $ids = [$ids];
        }

        return $this->recordReelViews($request, $ids);
    }

    /**
     * @param  list<mixed>  $videoIds
     */
    private function recordReelViews(Request $request, array $videoIds): JsonResponse
    {
        $viewer = Auth::guard('sanctum')->user();
        $userId = $viewer !== null ? (string) $viewer->id : null;
        $deviceId = ReelViewTracker::normalizeDeviceId($request->input('device_id'));

        if (ReelViewTracker::viewerKey($userId, $deviceId) === null) {
            return response()->json([
                'status' => false,
                'message' => 'device_id is required when not authenticated',
            ], 422);
        }

        if (! ReelViewTracker::tableReady()) {
            return response()->json([
                'status' => true,
                'recorded' => 0,
            ]);
        }

        $ids = [];
        foreach ($videoIds as $videoId) {
            $id = trim((string) $videoId);
            if ($id !== '') {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);

        if ($ids === []) {
            return response()->json([
                'status' => false,
                'message' => 'video_id is required',
            ], 422);
        }

        if (count($ids) > 20) {
            $ids = array_slice($ids, 0, 20);
        }

        $existing = Video::query()
            ->whereIn('id', $ids)
            ->where('is_soft_delete', 0)
            ->pluck('id')
            ->all();

        $recorded = ReelViewTracker::record($userId, $deviceId, $existing);

        return response()->json([
            'status' => true,
            'recorded' => $recorded,
            'video_ids' => array_values($existing),
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

        $isFirstNearMePage = $feed === self::FEED_NEAR_ME
            && $cursor['created_at'] === null
            && $cursor['system_id'] === null
            && ($cursor['unseen_phase'] ?? ReelViewTracker::PHASE_UNSEEN) !== ReelViewTracker::PHASE_SEEN
            && empty($cursor['seen_reset']);

        if ($isFirstNearMePage && ! empty($geoContext['geo_empty_country'])) {
            return [
                'items' => collect(),
                'has_more' => false,
                'next_cursor' => null,
                'geo_fallback' => false,
                'geo_empty_country' => true,
            ];
        }

        if (
            $isFirstNearMePage
            && ! $geoFallback
            && empty($geoContext['geo_empty_country'])
            && (
                ! empty($geoContext['geo_unresolved'])
                || ! $this->hasActiveNearMeGeoFilter($geoContext)
            )
        ) {
            $fallbackContext = $this->nearMeEmptyResultFallback($geoContext);
            $result = $this->executeReelsQuery(
                $feed,
                $cursor,
                $viewer,
                $fallbackContext,
                $feedContext
            );
            $result['geo_fallback'] = ! empty($fallbackContext['geo_fallback']);
            $result['geo_empty_country'] = ! empty($geoContext['geo_empty_country']);

            return $result;
        }

        $result = $this->executeReelsQuery($feed, $cursor, $viewer, $geoContext, $feedContext);

        if (
            $feed === self::FEED_NEAR_ME
            && $result['items']->isEmpty()
            && ! $geoFallback
            && $isFirstNearMePage
            && empty($geoContext['geo_empty_country'])
            && $this->hasActiveNearMeGeoFilter($geoContext)
        ) {
            $fallbackContext = $this->nearMeEmptyResultFallback($geoContext);
            $result = $this->executeReelsQuery(
                $feed,
                $cursor,
                $viewer,
                $fallbackContext,
                $feedContext
            );
            $result['geo_fallback'] = ! empty($fallbackContext['geo_fallback']);
            $result['geo_empty_country'] = ! empty($geoContext['geo_empty_country']);

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
        $query = Video::query()
            ->select('videos.*')
            ->with(['user:id,name,user_name,image'])
            ->withCount([
                'comments as comments_count' => fn ($q) => $q->where('status', 1),
                'saves as likes_count' => fn ($q) => $q->where('status', 1),
            ])
            ->where('videos.status', 1)
            ->where('videos.is_soft_delete', 0)
            ->whereIn('videos.publish_type', [1, 2]);
        $this->applyReelsPublishableMediaFilter($query);

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

        if (! empty($cursor['consumed_pin_id'])) {
            $query->where('videos.id', '!=', (string) $cursor['consumed_pin_id']);
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
                    'unseen_exhausted' => true,
                ];
            }

            $query->whereIn('videos.front_user_id', $followingIds);
        }

        if ($feed === self::FEED_USER) {
            $this->applyUserFeedFilters($query, $feedContext['user_id'], $feedContext['video_type'], $viewer);
        }

        $useDistanceSort = $this->shouldSortNearMeByDistance($feed, $geoContext);

        if ($feed === self::FEED_NEAR_ME && empty($geoContext['geo_fallback'])) {
            $this->applyNearMeItemGeoJoins(
                $query,
                $useDistanceSort,
                $geoContext['latitude'] !== null ? (float) $geoContext['latitude'] : null,
                $geoContext['longitude'] !== null ? (float) $geoContext['longitude'] : null,
            );
        }

        if ($this->shouldApplyGeoFilters($feed, $geoContext)) {
            $this->applyNearMeGeoFilters($query, $geoContext);
        }

        $sort = $feedContext['sort_by'];

        return $this->paginateReelsQuery(
            $query,
            $feed,
            $cursor,
            $viewer,
            $geoContext,
            $feedContext,
            $useDistanceSort,
            $sort
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Video>  $query
     * @param  array<string, mixed>  $cursor
     * @param  array<string, mixed>  $geoContext
     * @param  array<string, mixed>  $feedContext
     * @return array{items: Collection<int, Video>, has_more: bool, next_cursor: ?string, pinned_video_id: ?string, unseen_exhausted: bool}
     */
    private function paginateReelsQuery(
        $query,
        string $feed,
        array $cursor,
        mixed $viewer,
        array $geoContext,
        array $feedContext,
        bool $useDistanceSort,
        string $sort
    ): array {
        $perPage = $feedContext['per_page'];
        $unseenFirst = ! empty($feedContext['unseen_first']) && ! empty($feedContext['viewer_key']);
        $phase = $unseenFirst
            ? (string) ($cursor['unseen_phase'] ?? ReelViewTracker::PHASE_UNSEEN)
            : null;
        $unseenExhausted = ! $unseenFirst || $phase === ReelViewTracker::PHASE_SEEN;

        if ($unseenFirst && $phase === ReelViewTracker::PHASE_UNSEEN) {
            $unseenQuery = clone $query;
            ReelViewTracker::applyPhaseFilter($unseenQuery, $feedContext['viewer_key'], false);
            $this->applyReelsPageCursor($unseenQuery, $cursor, $feedContext, $geoContext, $useDistanceSort, $sort, true);
            $this->applyReelsPageOrder($unseenQuery, $useDistanceSort, $sort, $geoContext);

            $unseenRows = $unseenQuery->limit($perPage + 1)->get();
            $hasMoreUnseen = $unseenRows->count() > $perPage;
            $items = $unseenRows->take($perPage)->values();

            if ($hasMoreUnseen) {
                return $this->finalizeReelsPage(
                    $items,
                    true,
                    $feed,
                    $cursor,
                    $viewer,
                    $geoContext,
                    $feedContext,
                    ReelViewTracker::PHASE_UNSEEN,
                    false,
                    false
                );
            }

            $need = $perPage - $items->count();
            $seenQuery = clone $query;
            ReelViewTracker::applyPhaseFilter($seenQuery, $feedContext['viewer_key'], true);
            if ($items->isNotEmpty()) {
                $seenQuery->whereNotIn('videos.id', $items->pluck('id')->all());
            }
            $this->applyReelsPageOrder($seenQuery, $useDistanceSort, $sort, $geoContext);
            $seenLimit = ($need > 0 ? $need : 1) + 1;
            $seenRows = $seenQuery->limit($seenLimit)->get();

            if ($need > 0) {
                $items = $items->concat($seenRows->take($need))->values();
                $hasMore = $seenRows->count() > $need;
            } else {
                $hasMore = $seenRows->isNotEmpty();
            }

            return $this->finalizeReelsPage(
                $items,
                $hasMore,
                $feed,
                $cursor,
                $viewer,
                $geoContext,
                $feedContext,
                ReelViewTracker::PHASE_SEEN,
                true,
                $need === 0 && $hasMore
            );
        }

        if ($unseenFirst && $phase === ReelViewTracker::PHASE_SEEN) {
            ReelViewTracker::applyPhaseFilter($query, $feedContext['viewer_key'], true);
            $applyKeyset = empty($cursor['seen_reset']);
            $this->applyReelsPageCursor($query, $cursor, $feedContext, $geoContext, $useDistanceSort, $sort, $applyKeyset);
            $this->applyReelsPageOrder($query, $useDistanceSort, $sort, $geoContext);
            $rows = $query->limit($perPage + 1)->get();
            $hasMore = $rows->count() > $perPage;
            $items = $rows->take($perPage)->values();

            return $this->finalizeReelsPage(
                $items,
                $hasMore,
                $feed,
                $cursor,
                $viewer,
                $geoContext,
                $feedContext,
                ReelViewTracker::PHASE_SEEN,
                true,
                false
            );
        }

        $this->applyReelsPageCursor($query, $cursor, $feedContext, $geoContext, $useDistanceSort, $sort, true);
        $this->applyReelsPageOrder($query, $useDistanceSort, $sort, $geoContext);
        $rows = $query->limit($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        $items = $rows->take($perPage)->values();

        return $this->finalizeReelsPage(
            $items,
            $hasMore,
            $feed,
            $cursor,
            $viewer,
            $geoContext,
            $feedContext,
            null,
            $unseenExhausted,
            false
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Video>  $query
     * @param  array<string, mixed>  $cursor
     * @param  array<string, mixed>  $feedContext
     * @param  array<string, mixed>  $geoContext
     */
    private function applyReelsPageCursor(
        $query,
        array $cursor,
        array $feedContext,
        array $geoContext,
        bool $useDistanceSort,
        string $sort,
        bool $applyKeyset
    ): void {
        $anchorApplied = $this->applyAnchorOrCursor($query, $cursor, $feedContext);
        if ($anchorApplied || ! $applyKeyset) {
            return;
        }

        if ($useDistanceSort && $cursor['near_me_distance'] !== null) {
            $this->applyNearMeDistanceCursor(
                $query,
                $cursor,
                $sort,
                (float) $geoContext['latitude'],
                (float) $geoContext['longitude']
            );

            return;
        }

        if ($cursor['created_at'] !== null && $cursor['id'] !== null) {
            VideoFeedSort::applyKeysetCursor($query, $sort, $cursor['created_at'], $cursor['id']);

            return;
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
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Video>  $query
     * @param  array<string, mixed>  $geoContext
     */
    private function applyReelsPageOrder($query, bool $useDistanceSort, string $sort, array $geoContext): void
    {
        if ($useDistanceSort) {
            $this->applyNearMeOrder(
                $query,
                $sort,
                (float) $geoContext['latitude'],
                (float) $geoContext['longitude']
            );

            return;
        }

        VideoFeedSort::applyOrder($query, $sort);
    }

    /**
     * @param  Collection<int, Video>  $items
     * @param  array<string, mixed>  $cursor
     * @param  array<string, mixed>  $geoContext
     * @param  array<string, mixed>  $feedContext
     * @return array{items: Collection<int, Video>, has_more: bool, next_cursor: ?string, pinned_video_id: ?string, unseen_exhausted: bool}
     */
    private function finalizeReelsPage(
        Collection $items,
        bool $hasMore,
        string $feed,
        array $cursor,
        mixed $viewer,
        array $geoContext,
        array $feedContext,
        ?string $unseenPhase,
        bool $unseenExhausted,
        bool $seenReset
    ): array {
        $perPage = $feedContext['per_page'];
        $pinnedVideoId = null;
        $consumedPinId = ! empty($cursor['consumed_pin_id']) ? (string) $cursor['consumed_pin_id'] : null;

        if (($feedContext['pin_video_id'] ?? null) !== null) {
            $pinned = $this->resolvePinnedVideo($feedContext['pin_video_id'], $viewer, $feed, $geoContext);
            if ($pinned !== null) {
                $hadPin = $items->contains(fn (Video $video) => (string) $video->id === (string) $pinned->id);
                $items = $items
                    ->reject(fn (Video $video) => (string) $video->id === (string) $pinned->id)
                    ->prepend($pinned)
                    ->take($perPage)
                    ->values();
                $pinnedVideoId = (string) $pinned->id;
                $consumedPinId = $pinnedVideoId;
                if (! $hadPin) {
                    $hasMore = $hasMore || $items->count() >= $perPage;
                }
            }
        }

        $nextCursor = null;
        if ($hasMore && $items->isNotEmpty()) {
            $last = $items->last();
            if (
                $seenReset
                && $pinnedVideoId !== null
                && (string) $last->id === $pinnedVideoId
                && $items->count() > 1
            ) {
                $last = $items[1];
            }
            $nextCursor = $this->encodeNextCursor(
                $feed,
                $feedContext,
                $geoContext,
                $last,
                $consumedPinId,
                $unseenPhase,
                $seenReset
            );
        }

        return [
            'items' => $items,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
            'pinned_video_id' => $pinnedVideoId,
            'unseen_exhausted' => $unseenExhausted,
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
            ->where('is_soft_delete', 0);
        $this->applyReelsPublishableMediaFilter($anchorQuery, '');

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
    private function encodeNextCursor(
        string $feed,
        array $feedContext,
        array $geoContext,
        Video $last,
        ?string $consumedPinId = null,
        ?string $unseenPhase = null,
        bool $seenReset = false
    ): string {
        $cursorData = [
            'feed' => $feed,
            'id' => $last->id,
            'created_at' => $last->created_at?->toDateTimeString() ?? (string) $last->created_at,
            'sort_by' => $feedContext['sort_by'],
        ];

        if ($unseenPhase !== null) {
            $cursorData['phase'] = $unseenPhase;
            if ($seenReset) {
                $cursorData['seen_reset'] = true;
            }
        }

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

        if ($consumedPinId !== null && $consumedPinId !== '') {
            $cursorData['consumed_pin_id'] = $consumedPinId;
        }

        if (! empty($feedContext['device_id'])) {
            $cursorData['device_id'] = $feedContext['device_id'];
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

        $isFirstPage = $cursor['created_at'] === null
            && $cursor['system_id'] === null
            && ($cursor['unseen_phase'] ?? ReelViewTracker::PHASE_UNSEEN) !== ReelViewTracker::PHASE_SEEN
            && empty($cursor['seen_reset']);
        $pinVideoId = null;
        if ($isFirstPage && $request->filled('pin_video_id') && in_array($feed, [self::FEED_GENERAL, self::FEED_NEAR_ME], true)) {
            $pinVideoId = (string) $request->input('pin_video_id');
        }

        $deviceId = ReelViewTracker::normalizeDeviceId(
            $request->input('device_id') ?? ($cursor['device_id'] ?? null)
        );
        $authUser = Auth::guard('sanctum')->user();
        $viewerKey = ReelViewTracker::viewerKey(
            $authUser !== null ? (string) $authUser->id : null,
            $deviceId
        );
        $homeFeed = in_array($feed, [self::FEED_GENERAL, self::FEED_NEAR_ME, self::FEED_FOLLOWING], true);
        $wantsUnseen = $request->boolean('unseen_first')
            || in_array($cursor['unseen_phase'] ?? null, [ReelViewTracker::PHASE_UNSEEN, ReelViewTracker::PHASE_SEEN], true);
        $unseenFirst = $homeFeed && $wantsUnseen && $viewerKey !== null && ReelViewTracker::tableReady();

        return [
            'feed' => $feed,
            'user_id' => $userId,
            'video_type' => $videoType,
            'per_page' => $perPage,
            'anchor_id' => $anchorId,
            'sort_by' => VideoFeedSort::fromRequest($request, $cursor),
            'pin_video_id' => $pinVideoId,
            'unseen_first' => $unseenFirst,
            'viewer_key' => $unseenFirst ? $viewerKey : null,
            'device_id' => $deviceId,
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

        if ($feed === self::FEED_GENERAL) {
            return $this->resolveGeneralLocationGeoContext($request);
        }

        if ($feed !== self::FEED_NEAR_ME) {
            return $empty;
        }

        if (! empty($cursor['geo_fallback'])) {
            return $this->generalNearMeFallbackGeoContext();
        }

        $lat = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $lng = $request->filled('longitude') ? (float) $request->input('longitude') : null;
        $clientCity = $request->filled('city') ? (int) $request->input('city') : null;
        $manualCity = $clientCity;

        if ($lat !== null && $lng !== null) {
            $gpsCountryId = FeedSocialCache::countryIdFromCoords($lat, $lng);
            $manualCity = FeedSocialCache::trustedManualCity($clientCity, $gpsCountryId);
            $scope = $this->parseGeoScope($request);

            if ($scope === self::GEO_SCOPE_LOCAL) {
                $radiusKm = $this->parseRadiusKm($request);
                $citiesIds = FeedSocialCache::localCityIds($lat, $lng, $radiusKm);
                $city = FeedSocialCache::nearestCityId($lat, $lng);

                return $this->withGeoCityName([
                    'cities_ids' => $citiesIds,
                    'city' => $city,
                    'country_id' => $gpsCountryId,
                    'geo_scope' => self::GEO_SCOPE_LOCAL,
                    'geo_radius_km' => $radiusKm,
                    'geo_fallback' => false,
                    'geo_unresolved' => false,
                    'geo_expanded' => false,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'hash' => sha1('local:'.round($lat, 3).':'.round($lng, 3).':'.$radiusKm.':'.implode(',', $citiesIds)),
                ]);
            }

            if ($scope === self::GEO_SCOPE_GLOBAL) {
                return $this->withGeoCityName([
                    'cities_ids' => [],
                    'city' => FeedSocialCache::nearestCityId($lat, $lng),
                    'country_id' => 0,
                    'geo_scope' => self::GEO_SCOPE_GLOBAL,
                    'geo_radius_km' => null,
                    'geo_fallback' => false,
                    'geo_unresolved' => false,
                    'geo_expanded' => false,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'hash' => sha1('global:'.round($lat, 3).':'.round($lng, 3)),
                ]);
            }

            if ($scope === self::GEO_SCOPE_COUNTRY) {
                $requestedCountry = $request->filled('country') ? (int) $request->input('country') : 0;
                $countryId = ($requestedCountry > 0 && $requestedCountry === $gpsCountryId)
                    ? $requestedCountry
                    : $gpsCountryId;

                return $this->withGeoCityName([
                    'cities_ids' => [],
                    'city' => FeedSocialCache::nearestCityId($lat, $lng),
                    'country_id' => $countryId,
                    'geo_scope' => self::GEO_SCOPE_COUNTRY,
                    'geo_radius_km' => null,
                    'geo_fallback' => false,
                    'geo_unresolved' => false,
                    'geo_expanded' => false,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'hash' => sha1('country:'.$countryId.':'.round($lat, 3).':'.round($lng, 3)),
                ]);
            }

            $nearMe = FeedSocialCache::nearMeCityIds($lat, $lng, $manualCity);

            return $this->nearMeCityGeoContext($nearMe, $lat, $lng);
        }

        if ($manualCity !== null) {
            $cityRow = DB::table('cities')->where('id', $manualCity)->first(['id', 'latitude', 'longitude']);

            if ($cityRow && $cityRow->latitude !== null && $cityRow->longitude !== null) {
                $nearMe = FeedSocialCache::nearMeCityIds(
                    (float) $cityRow->latitude,
                    (float) $cityRow->longitude,
                    $manualCity
                );

                return $this->nearMeCityGeoContext(
                    $nearMe,
                    (float) $cityRow->latitude,
                    (float) $cityRow->longitude
                );
            }

            $citiesIds = FeedSocialCache::cityGroupIds($manualCity);
            $countryId = (int) (DB::table('cities')->where('id', $manualCity)->value('country_id') ?? 0);

            return $this->withGeoCityName([
                'cities_ids' => $citiesIds,
                'city' => $manualCity,
                'country_id' => $countryId,
                'geo_scope' => self::GEO_SCOPE_CITY,
                'geo_radius_km' => null,
                'geo_fallback' => false,
                'geo_unresolved' => false,
                'geo_expanded' => false,
                'latitude' => null,
                'longitude' => null,
                'hash' => sha1('city:'.$manualCity.':'.implode(',', $citiesIds)),
            ]);
        }

        return array_merge($empty, [
            'geo_unresolved' => true,
            'hash' => 'unresolved',
        ]);
    }

    private function resolveGeneralLocationGeoContext(Request $request): array
    {
        $locationParams = FeedSocialCache::locationParamsFromRequest($request);
        $hasCountry = $locationParams['country'] !== null && $locationParams['country'] !== '';
        $hasCity = $locationParams['city'] !== null && $locationParams['city'] !== '';

        if (! $hasCountry && ! $hasCity) {
            return $this->emptyGeoContext();
        }

        $location = FeedSocialCache::resolveLocationFilter(
            $locationParams['country'],
            $locationParams['city'],
        );

        $countryId = (int) ($location['country'] ?? 0);
        $cityId = (int) ($location['city'] ?? 0);
        $citiesIds = $location['cities_ids'] ?? [];

        if ($cityId > 0 && ! empty($citiesIds)) {
            return [
                'cities_ids' => $citiesIds,
                'city' => $cityId,
                'country_id' => $countryId,
                'geo_scope' => self::GEO_SCOPE_CITY,
                'geo_radius_km' => null,
                'geo_fallback' => false,
                'geo_unresolved' => false,
                'geo_expanded' => false,
                'latitude' => null,
                'longitude' => null,
                'hash' => sha1('general:city:'.$cityId.':'.implode(',', $citiesIds)),
            ];
        }

        if ($countryId > 0) {
            return [
                'cities_ids' => [],
                'city' => 0,
                'country_id' => $countryId,
                'geo_scope' => self::GEO_SCOPE_COUNTRY,
                'geo_radius_km' => null,
                'geo_fallback' => false,
                'geo_unresolved' => false,
                'geo_expanded' => false,
                'latitude' => null,
                'longitude' => null,
                'hash' => sha1('general:country:'.$countryId),
            ];
        }

        return $this->emptyGeoContext();
    }

    private function shouldApplyGeoFilters(string $feed, array $geoContext): bool
    {
        if (! empty($geoContext['geo_fallback']) || $geoContext['geo_scope'] === self::GEO_SCOPE_NONE) {
            return false;
        }

        if ($feed === self::FEED_NEAR_ME) {
            return true;
        }

        if ($feed === self::FEED_GENERAL) {
            return $this->hasActiveNearMeGeoFilter($geoContext);
        }

        return false;
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
            'geo_unresolved' => false,
            'geo_expanded' => false,
            'geo_empty_country' => false,
            'latitude' => null,
            'longitude' => null,
            'hash' => 'none',
        ];
    }

    /**
     * @param  array{city: int, cities_ids: array<int|string>, expanded: bool, hash: string}  $nearMe
     * @return array{
     *     cities_ids: array<int|string>,
     *     city: int,
     *     country_id: int,
     *     geo_scope: string,
     *     geo_radius_km: ?float,
     *     geo_fallback: bool,
     *     geo_unresolved: bool,
     *     geo_expanded: bool,
     *     latitude: ?float,
     *     longitude: ?float,
     *     hash: string
     * }
     */
    private function nearMeCityGeoContext(array $nearMe, float $lat, float $lng): array
    {
        return $this->withGeoCityName([
            'cities_ids' => $nearMe['cities_ids'],
            'city' => $nearMe['city'],
            'country_id' => FeedSocialCache::countryIdFromCoords($lat, $lng),
            'geo_scope' => self::GEO_SCOPE_CITY,
            'geo_radius_km' => $nearMe['expanded'] ? 120.0 : null,
            'geo_fallback' => false,
            'geo_unresolved' => false,
            'geo_expanded' => $nearMe['expanded'],
            'latitude' => $lat,
            'longitude' => $lng,
            'hash' => $nearMe['hash'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function withGeoCityName(array $context): array
    {
        $cityId = (int) ($context['city'] ?? 0);
        if ($cityId > 0) {
            $context['geo_city_name'] = FeedSocialCache::cityName($cityId);
        }

        $countryId = (int) ($context['country_id'] ?? 0);
        if ($countryId > 0) {
            $context['geo_country_name'] = FeedSocialCache::countryName($countryId);
            $context['geo_empty_country'] = FeedSocialCache::publishedVideoCountInCountry($countryId) === 0;
        } else {
            $context['geo_empty_country'] = false;
        }

        return $context;
    }

    /**
     * Prefer same-country videos over the worldwide general dump when Near Me
     * has no city matches. An empty country stays empty so the app can offer
     * "browse another country".
     *
     * @param  array<string, mixed>  $geoContext
     * @return array<string, mixed>
     */
    private function nearMeEmptyResultFallback(array $geoContext): array
    {
        $countryId = (int) ($geoContext['country_id'] ?? 0);
        if ($countryId > 0 && empty($geoContext['geo_empty_country'])) {
            return $this->withGeoCityName([
                'cities_ids' => [],
                'city' => 0,
                'country_id' => $countryId,
                'geo_scope' => self::GEO_SCOPE_COUNTRY,
                'geo_radius_km' => null,
                'geo_fallback' => false,
                'geo_unresolved' => false,
                'geo_expanded' => false,
                'latitude' => $geoContext['latitude'] ?? null,
                'longitude' => $geoContext['longitude'] ?? null,
                'hash' => sha1('country-fallback:'.$countryId),
            ]);
        }

        return $this->generalNearMeFallbackGeoContext();
    }

    /**
     * @return array{
     *     cities_ids: array<int|string>,
     *     city: int,
     *     country_id: int,
     *     geo_scope: string,
     *     geo_radius_km: ?float,
     *     geo_fallback: bool,
     *     geo_unresolved: bool,
     *     geo_expanded: bool,
     *     latitude: ?float,
     *     longitude: ?float,
     *     hash: string
     * }
     */
    private function generalNearMeFallbackGeoContext(): array
    {
        return array_merge($this->emptyGeoContext(), [
            'geo_fallback' => true,
            'hash' => 'fallback',
        ]);
    }

    /**
     * @param  array{
     *     cities_ids: array<int|string>,
     *     city: int,
     *     country_id: int,
     *     geo_scope: string,
     *     geo_radius_km: ?float,
     *     geo_fallback: bool,
     *     geo_unresolved: bool,
     *     geo_expanded: bool,
     *     latitude: ?float,
     *     longitude: ?float,
     *     hash: string
     * }  $geoContext
     */
    private function hasActiveNearMeGeoFilter(array $geoContext): bool
    {
        if (! empty($geoContext['geo_fallback']) || ! empty($geoContext['geo_unresolved'])) {
            return false;
        }

        if ($geoContext['geo_scope'] === self::GEO_SCOPE_GLOBAL) {
            return false;
        }

        if ($geoContext['geo_scope'] === self::GEO_SCOPE_CITY || $geoContext['geo_scope'] === self::GEO_SCOPE_LOCAL) {
            return ! empty($geoContext['cities_ids']);
        }

        if ($geoContext['geo_scope'] === self::GEO_SCOPE_COUNTRY) {
            return $geoContext['country_id'] > 0;
        }

        return false;
    }

    private function parseGeoScope(Request $request): string
    {
        if (! $request->filled('scope')) {
            return self::GEO_SCOPE_CITY;
        }

        $scope = strtolower((string) $request->input('scope'));

        return in_array($scope, [self::GEO_SCOPE_LOCAL, self::GEO_SCOPE_COUNTRY, self::GEO_SCOPE_GLOBAL], true)
            ? $scope
            : self::GEO_SCOPE_CITY;
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
    private function applyNearMeItemGeoJoins($query, bool $withDistance, ?float $lat, ?float $lng): void
    {
        if (! $this->queryHasJoinAlias($query, 'near_me_city')) {
            $query->leftJoin('cities as near_me_city', 'near_me_city.id', '=', 'videos.city');
        }

        if (! $this->queryHasJoinAlias($query, 'ba')) {
            $query->leftJoin('business_account_additional_data as ba', 'ba.front_user_id', '=', 'videos.front_user_id');
        }

        $query->addSelect(
            'near_me_city.name as near_me_city_name',
            'ba.location as near_me_location',
            'ba.latitude as near_me_latitude',
            'ba.longitude as near_me_longitude',
        );

        if ($withDistance && $lat !== null && $lng !== null) {
            $query->addSelect(DB::raw($this->nearMeDistanceExpression($lat, $lng).' AS near_me_distance'));
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Video>|\Illuminate\Database\Query\Builder  $query
     */
    private function queryHasJoinAlias($query, string $alias): bool
    {
        $joins = $query->getQuery()->joins ?? [];

        foreach ($joins as $join) {
            if (is_string($join->table) && str_contains($join->table, $alias)) {
                return true;
            }
        }

        return false;
    }

    private function nearMeDistanceExpression(float $lat, float $lng): string
    {
        $businessDistanceSql = FeedSocialCache::haversineDistanceSql(
            $lat,
            $lng,
            'ba.latitude',
            'ba.longitude'
        );

        $cityDistanceSql = FeedSocialCache::haversineDistanceSql(
            $lat,
            $lng,
            'COALESCE(near_me_city.latitude, 0)',
            'COALESCE(near_me_city.longitude, 0)'
        );

        return "CASE
            WHEN ba.latitude IS NOT NULL AND ba.longitude IS NOT NULL
                 AND ba.latitude != 0 AND ba.longitude != 0
            THEN {$businessDistanceSql}
            WHEN near_me_city.latitude IS NOT NULL
            THEN {$cityDistanceSql}
            ELSE 99999
        END";
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

        $emptyCountry = ! empty($payload['geo_empty_country']) || ! empty($geoContext['geo_empty_country']);
        $countryId = (int) ($geoContext['country_id'] ?? 0);

        if (! empty($payload['geo_fallback'])) {
            return [
                'geo_scope' => self::GEO_SCOPE_NONE,
                'geo_radius_km' => null,
                'geo_empty_country' => $emptyCountry,
                'geo_country_id' => $countryId > 0 ? $countryId : null,
                'geo_country_name' => $geoContext['geo_country_name'] ?? null,
            ];
        }

        $meta = [
            'geo_scope' => $geoContext['geo_scope'],
            'geo_radius_km' => $geoContext['geo_radius_km'],
            'geo_empty_country' => $emptyCountry,
        ];

        if ($countryId > 0) {
            $meta['geo_country_id'] = $countryId;
        }

        if (! empty($geoContext['geo_country_name'])) {
            $meta['geo_country_name'] = (string) $geoContext['geo_country_name'];
        }

        if (! empty($geoContext['geo_expanded'])) {
            $meta['geo_expanded'] = true;
        }

        if (! empty($geoContext['city'])) {
            $meta['geo_city_id'] = (int) $geoContext['city'];
        }

        if (! empty($geoContext['geo_city_name'])) {
            $meta['geo_city_name'] = (string) $geoContext['geo_city_name'];
        }

        return $meta;
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
            'consumed_pin_id' => null,
            'unseen_phase' => null,
            'seen_reset' => false,
            'device_id' => null,
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

        if (! is_array($data)) {
            return $empty;
        }

        $hasPhase = isset($data['phase']) && in_array($data['phase'], [ReelViewTracker::PHASE_UNSEEN, ReelViewTracker::PHASE_SEEN], true);
        if (! isset($data['id']) && ! $hasPhase && empty($data['seen_reset'])) {
            return $empty;
        }

        $createdAt = isset($data['created_at']) ? (string) $data['created_at'] : null;
        $systemId = isset($data['system_id']) ? (int) $data['system_id'] : null;
        $phase = isset($data['phase']) && $data['phase'] === ReelViewTracker::PHASE_SEEN
            ? ReelViewTracker::PHASE_SEEN
            : (isset($data['phase']) && $data['phase'] === ReelViewTracker::PHASE_UNSEEN
                ? ReelViewTracker::PHASE_UNSEEN
                : null);
        $seenReset = ! empty($data['seen_reset']);

        if ($createdAt === null && $systemId === null && $phase !== ReelViewTracker::PHASE_SEEN && ! $seenReset) {
            return $empty;
        }

        if ($phase === ReelViewTracker::PHASE_SEEN && $createdAt === null && $systemId === null) {
            $seenReset = true;
        }

        return [
            'cache_key' => sha1((string) $rawCursor),
            'system_id' => $systemId,
            'created_at' => $createdAt,
            'id' => isset($data['id']) ? (string) $data['id'] : null,
            'near_me_distance' => isset($data['near_me_distance']) ? (float) $data['near_me_distance'] : null,
            'sort_by' => VideoFeedSort::resolve($data['sort_by'] ?? null),
            'geo_fallback' => ! empty($data['geo_fallback']),
            'feed' => isset($data['feed']) ? (string) $data['feed'] : null,
            'user_id' => isset($data['user_id']) ? (string) $data['user_id'] : null,
            'video_type' => isset($data['video_type']) ? (int) $data['video_type'] : null,
            'consumed_pin_id' => isset($data['consumed_pin_id']) ? (string) $data['consumed_pin_id'] : null,
            'unseen_phase' => $phase,
            'seen_reset' => $seenReset,
            'device_id' => isset($data['device_id']) ? (string) $data['device_id'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $geoContext
     * @param  array<string, mixed>  $feedContext
     */
    private function loadReelsPage(string $feed, array $cursor, mixed $viewer, array $geoContext, array $feedContext): array
    {
        $fetch = fn () => $this->fetchReelsPage($feed, $cursor, $viewer, $geoContext, $feedContext);

        if (! empty($feedContext['unseen_first'])) {
            return $fetch();
        }

        $cacheKey = $this->feedCacheKey($feed, $cursor['cache_key'], $viewer, $geoContext, $feedContext);

        return $this->rememberFeedPage($cacheKey, $fetch);
    }

    /**
     * @param  array<string, mixed>  $feedContext
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function unseenFirstMeta(array $feedContext, array $payload): array
    {
        if (empty($feedContext['unseen_first'])) {
            return [];
        }

        return [
            'unseen_first' => true,
            'unseen_exhausted' => ! empty($payload['unseen_exhausted']),
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

        $geoPart = in_array($feed, [self::FEED_NEAR_ME, self::FEED_GENERAL], true) && $geoContext['geo_scope'] !== self::GEO_SCOPE_NONE
            ? '_g_'.$geoContext['hash']
            : '';

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

        $pinPart = '';
        if (($feedContext['pin_video_id'] ?? null) !== null && $cursorKey === '_start') {
            $pinPart = '_pin_'.$feedContext['pin_video_id'];
        }

        return 'reels_feed_'.$feed.'_'.$viewerPart.'_b_'.$blockedHash.$geoPart.$followingPart.$userPart.'_s_'.$feedContext['sort_by'].$pinPart.'_'.$cursorKey;
    }

    /**
     * @template T
     * @param  \Closure(): T  $callback
     * @return T
     */


    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Video>|\Illuminate\Database\Query\Builder  $query
     */
    private function applyReelsPublishableMediaFilter($query, string $tableAlias = 'videos'): void
    {
        $videoCol = $tableAlias !== '' ? $tableAlias.'.video' : 'video';
        $imageCol = $tableAlias !== '' ? $tableAlias.'.image' : 'image';
        $isImageCol = $tableAlias !== '' ? $tableAlias.'.is_image' : 'is_image';

        $query->where(function ($q) use ($videoCol, $imageCol, $isImageCol) {
            $q->where(function ($q2) use ($videoCol) {
                $q2->whereNotNull($videoCol)
                    ->where($videoCol, '!=', '');
            })->orWhere(function ($q2) use ($imageCol, $isImageCol) {
                $q2->where($isImageCol, 1)
                    ->whereNotNull($imageCol)
                    ->where($imageCol, '!=', '');
            });
        });
    }

    private function resolvePinnedVideo(?string $pinVideoId, mixed $viewer, string $feed, array $geoContext): ?Video
    {
        if ($pinVideoId === null || $pinVideoId === '' || $viewer === null) {
            return null;
        }

        if (! in_array($feed, [self::FEED_GENERAL, self::FEED_NEAR_ME], true)) {
            return null;
        }

        $query = Video::query()
            ->select('videos.*')
            ->with(['user:id,name,user_name,image'])
            ->withCount([
                'comments as comments_count' => fn ($q) => $q->where('status', 1),
                'saves as likes_count' => fn ($q) => $q->where('status', 1),
            ])
            ->where('videos.id', $pinVideoId)
            ->where('videos.front_user_id', $viewer->id)
            ->where('videos.status', 1)
            ->where('videos.is_soft_delete', 0)
            ->whereIn('videos.publish_type', [1, 2])
            ->where('videos.created_at', '>=', now()->subHours(self::PIN_MAX_AGE_HOURS));
        $this->applyReelsPublishableMediaFilter($query);

        if (\Illuminate\Support\Facades\Schema::hasColumn('videos', 'transcode_status')) {
            $query->where(function ($q) {
                $q->where('videos.transcode_status', 'ready')
                    ->orWhere('videos.is_image', 1);
            });
        }

        if ($this->shouldApplyGeoFilters($feed, $geoContext)) {
            $this->applyNearMeGeoFilters($query, $geoContext);
        }

        if ($feed === self::FEED_NEAR_ME && empty($geoContext['geo_fallback'])) {
            $this->applyNearMeItemGeoJoins(
                $query,
                $this->shouldSortNearMeByDistance($feed, $geoContext),
                $geoContext['latitude'] !== null ? (float) $geoContext['latitude'] : null,
                $geoContext['longitude'] !== null ? (float) $geoContext['longitude'] : null,
            );
        }

        return $query->first();
    }

    private function rememberFeedPage(string $cacheKey, \Closure $callback): mixed
    {
        try {
            return Cache::remember($cacheKey, 30, $callback);
        } catch (\Throwable) {
            return $callback();
        }
    }
}
