<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Shared CookCache keys for feed/search social + geo lookups.
 */
class FeedSocialCache
{
    public static function nearestCityId(float $lat, float $lng): int
    {
        $cacheKey = 'feed:nearest_city:'.round($lat, 3).':'.round($lng, 3);

        return (int) CookCache::rememberLocked($cacheKey, [600, 3600], function () use ($lat, $lng) {
            foreach ([10, 25, 50] as $radiusKm) {
                $nearestCity = DB::table('cities')
                    ->select('id', DB::raw("(
                        6371 * acos(
                            cos(radians($lat)) *
                            cos(radians(latitude)) *
                            cos(radians(longitude) - radians($lng)) +
                            sin(radians($lat)) *
                            sin(radians(latitude))
                        )
                    ) AS distance"))
                    ->having('distance', '<', $radiusKm)
                    ->orderBy('distance', 'asc')
                    ->first();

                if ($nearestCity) {
                    return (int) $nearestCity->id;
                }
            }

            return 0;
        });
    }

    /**
     * @return array<int|string>
     */
    public static function cityGroupIds(int $city): array
    {
        if ($city === 0) {
            return [];
        }

        return CookCache::remember('feed:city_group:'.$city, [900, 86400], function () use ($city) {
            $city_group = DB::table('cities_groups')->whereRaw('FIND_IN_SET(?, cities)', [$city])->first();
            if (! empty($city_group)) {
                return explode(',', $city_group->cities);
            }

            return [$city];
        });
    }

    public static function countryIdFromCoords(float $lat, float $lng): int
    {
        $cityId = self::nearestCityId($lat, $lng);
        if ($cityId === 0) {
            return 0;
        }

        return (int) (DB::table('cities')->where('id', $cityId)->value('country_id') ?? 0);
    }

    /**
     * Cities within radius that have published videos (for scope=local).
     *
     * @return list<int|string>
     */
    public static function localCityIds(float $lat, float $lng, float $radiusKm): array
    {
        $radiusKm = max(1.0, $radiusKm);
        $ids = self::cityIdsWithVideosWithinRadius($lat, $lng, $radiusKm);

        if (! empty($ids)) {
            return $ids;
        }

        $city = self::nearestCityId($lat, $lng);

        return $city > 0 ? self::cityGroupIds($city) : [];
    }

    public static function haversineDistanceSql(float $lat, float $lng, string $latCol, string $lngCol): string
    {
        return "(6371 * acos(
            LEAST(1, GREATEST(-1,
                cos(radians({$lat})) *
                cos(radians({$latCol})) *
                cos(radians({$lngCol}) - radians({$lng})) +
                sin(radians({$lat})) *
                sin(radians({$latCol}))
            ))
        ))";
    }

    /**
     * Resolve city IDs for Near Me: exact city group first, then expand to
     * nearby cities that actually have published videos before callers fall
     * back to the general feed.
     *
     * @return array{city: int, cities_ids: array<int|string>, expanded: bool, hash: string}
     */
    public static function nearMeCityIds(float $lat, float $lng, ?int $manualCity = null): array
    {
        $latKey = round($lat, 3);
        $lngKey = round($lng, 3);
        $manualKey = $manualCity ?? 0;
        $cacheKey = 'feed:near_me_cities:'.$manualKey.':'.$latKey.':'.$lngKey;

        return CookCache::remember($cacheKey, [300, 1800], function () use ($lat, $lng, $manualCity) {
            $city = $manualCity ?? self::nearestCityId($lat, $lng);
            $primaryIds = $city > 0 ? self::cityGroupIds($city) : [];

            if (! empty($primaryIds) && self::publishedVideoCountInCities($primaryIds) > 0) {
                return self::nearMeCityPayload($city, $primaryIds, false, $lat, $lng);
            }

            foreach ([50, 80, 120] as $radiusKm) {
                $expandedIds = self::cityIdsWithVideosWithinRadius($lat, $lng, $radiusKm);

                if (! empty($expandedIds)) {
                    return self::nearMeCityPayload($city, $expandedIds, true, $lat, $lng);
                }
            }

            return self::nearMeCityPayload($city, $primaryIds, false, $lat, $lng);
        });
    }

    /**
     * @param  array<int|string>  $cityIds
     * @return array{city: int, cities_ids: array<int|string>, expanded: bool, hash: string}
     */
    private static function nearMeCityPayload(int $city, array $cityIds, bool $expanded, float $lat, float $lng): array
    {
        sort($cityIds);

        return [
            'city' => $city,
            'cities_ids' => $cityIds,
            'expanded' => $expanded,
            'hash' => sha1($city.':'.round($lat, 3).':'.round($lng, 3).':'.($expanded ? 'expanded:' : 'exact:').implode(',', $cityIds)),
        ];
    }

    /**
     * @param  array<int|string>  $cityIds
     */
    private static function publishedVideoCountInCities(array $cityIds): int
    {
        if (empty($cityIds)) {
            return 0;
        }

        return (int) DB::table('videos')
            ->where('status', 1)
            ->where('is_soft_delete', 0)
            ->whereIn('city', $cityIds)
            ->count();
    }

    /**
     * Cities within radius that have at least one published video.
     *
     * @return list<int|string>
     */
    private static function cityIdsWithVideosWithinRadius(float $lat, float $lng, float $radiusKm): array
    {
        return DB::table('cities as c')
            ->join('videos as v', 'v.city', '=', 'c.id')
            ->where('v.status', 1)
            ->where('v.is_soft_delete', 0)
            ->select('c.id', DB::raw("(
                6371 * acos(
                    cos(radians($lat)) *
                    cos(radians(c.latitude)) *
                    cos(radians(c.longitude) - radians($lng)) +
                    sin(radians($lat)) *
                    sin(radians(c.latitude))
                )
            ) AS distance"))
            ->groupBy('c.id', 'c.latitude', 'c.longitude')
            ->having('distance', '<', $radiusKm)
            ->orderBy('distance')
            ->pluck('c.id')
            ->all();
    }

    /**
     * @return list<int|string>
     */
    public static function blockedUserIds(int|string $userId): array
    {
        return CookCache::remember(
            'feed:blocked_users:'.$userId,
            [60, 300],
            fn () => DB::table('blocked_users')
                ->where('blocked_by', $userId)
                ->pluck('blocked_user')
                ->all()
        );
    }

    /**
     * @return list<int|string>
     */
    public static function followingIds(int|string $userId): array
    {
        return CookCache::remember(
            'feed:following_ids:'.$userId,
            [60, 300],
            fn () => DB::table('followers')
                ->where('follower_id', $userId)
                ->pluck('following_id')
                ->all()
        );
    }
}
