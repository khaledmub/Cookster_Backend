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
