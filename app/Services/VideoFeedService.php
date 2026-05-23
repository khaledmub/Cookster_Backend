<?php

namespace App\Services;

use App\Helpers\AppHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VideoFeedService
{
    private const NORMAL_BATCH = 5;

    private static function videoSelectColumns(): array
    {
        return [
            'v.*',
            'sv.sponsor_type',
            'video_type_description.name as video_type_name',
            'u.name as user_name',
            'u.email as user_email',
            'u.image as user_image',
            'ba.contact_phone',
            'ba.contact_email',
            'ba.website',
            'ba.location',
            'ba.latitude',
            'ba.longitude',
            DB::raw('COALESCE(followers.followers_count, 0) as followers_count'),
            DB::raw('COALESCE(following.following_count, 0) as following_count'),
        ];
    }

    public function legacyList(Request $request, $user): array
    {
        $context = $this->buildFeedContext($request, $user);
        $queries = $this->buildVideoQueries($context);

        $feedSeed = (int) ($request->input('feed_seed') ?: random_int(1, 999999));
        $normalVideos = $this->fetchVideos($queries['normal'], $feedSeed);
        $sponsoredVideos = $this->fetchVideos($queries['sponsored'], $feedSeed);
        $premiumSponsoredVideos = $this->fetchVideos($queries['premium'], $feedSeed);

        $finalList = $this->mergeVideos(
            $normalVideos,
            $sponsoredVideos,
            $premiumSponsoredVideos,
            0,
            0,
            0,
            PHP_INT_MAX
        )['videos'];

        AppHelper::decorateVideoIterable($finalList);

        return [
            'status' => true,
            'videos' => $finalList,
        ];
    }

    public function paginatedList(Request $request, $user): array
    {
        $context = $this->buildFeedContext($request, $user);
        $queries = $this->buildVideoQueries($context);

        $perPage = min(max((int) ($request->input('per_page') ?: 15), 1), 30);
        $page = max((int) ($request->input('page') ?: 1), 1);
        $feedSeed = (int) ($request->input('feed_seed') ?: random_int(1, 999999));

        $premiumIndex = max((int) ($request->input('premium_index') ?: 0), 0);
        $sponsoredIndex = max((int) ($request->input('sponsored_index') ?: 0), 0);
        $patternIndex = max((int) ($request->input('pattern_index') ?: 0), 0);
        $normalOffset = max((int) ($request->input('normal_offset') ?: 0), 0);

        if ($page > 1 && $normalOffset === 0) {
            $normalOffset = ($page - 1) * (int) ceil($perPage * 0.85);
        }

        $normalBuffer = $this->fetchVideos(
            $queries['normal'],
            $feedSeed,
            $normalOffset,
            $perPage * 3
        );
        $sponsoredCap = max($perPage, 20);
        $sponsoredVideos = $this->fetchVideos($queries['sponsored'], $feedSeed, 0, $sponsoredCap);
        $premiumSponsoredVideos = $this->fetchVideos($queries['premium'], $feedSeed, 0, $sponsoredCap);

        $merge = $this->mergeVideos(
            $normalBuffer,
            $sponsoredVideos,
            $premiumSponsoredVideos,
            $premiumIndex,
            $sponsoredIndex,
            $patternIndex,
            $perPage
        );

        $videos = $merge['videos'];
        $nextNormalOffset = $normalOffset + $merge['consumed_normals'];
        $hasMore = count($normalBuffer) > $merge['consumed_normals'];

        AppHelper::decorateVideoIterable($videos);

        $nextCursor = base64_encode(json_encode([
            'normal_offset' => $nextNormalOffset,
            'premium_index' => $merge['premium_index'],
            'sponsored_index' => $merge['sponsored_index'],
            'pattern_index' => $merge['pattern_index'],
            'feed_seed' => $feedSeed,
        ]));

        return [
            'status' => true,
            'videos' => $videos,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => $hasMore && count($videos) > 0,
                'next_cursor' => $hasMore && count($videos) > 0 ? $nextCursor : null,
                'feed_seed' => $feedSeed,
                'premium_index' => $merge['premium_index'],
                'sponsored_index' => $merge['sponsored_index'],
                'pattern_index' => $merge['pattern_index'],
                'normal_offset' => $nextNormalOffset,
            ],
        ];
    }

    /**
     * @return array{city:int,country:int,cities_ids:array,input:array,user:mixed}
     */
    private function buildFeedContext(Request $request, $user): array
    {
        $input = $request->all();
        $city = 0;
        $country = 0;
        $cities_ids = [];

        if (isset($input['city']) && $input['city'] != '') {
            $city = (int) $input['city'];
        } elseif (isset($input['latitude'], $input['longitude']) && $input['latitude'] != '' && $input['longitude'] != '') {
            $city = $this->resolveNearestCityId((float) $input['latitude'], (float) $input['longitude']);
        }

        if ($city > 0) {
            $cities_ids = $this->resolveCityGroupIds($city);
        }

        return compact('city', 'country', 'cities_ids', 'input', 'user');
    }

    private function resolveNearestCityId(float $lat, float $lng): int
    {
        $cacheKey = 'feed_nearest_city_'.round($lat, 3).'_'.round($lng, 3);

        return (int) Cache::remember($cacheKey, now()->addMinutes(10), function () use ($lat, $lng) {
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
    private function resolveCityGroupIds(int $city): array
    {
        return Cache::remember('feed_city_group_'.$city, now()->addMinutes(15), function () use ($city) {
            $city_group = DB::table('cities_groups')->whereRaw('FIND_IN_SET(?, cities)', [$city])->first();
            if (! empty($city_group)) {
                return explode(',', $city_group->cities);
            }

            return [$city];
        });
    }

    /**
     * @return array{normal:\Illuminate\Database\Query\Builder,sponsored:\Illuminate\Database\Query\Builder,premium:\Illuminate\Database\Query\Builder}
     */
    private function buildVideoQueries(array $context): array
    {
        $input = $context['input'];
        $user = $context['user'];
        $cities_ids = $context['cities_ids'];
        $country = $context['country'];

        $baseQuery = DB::table('videos as v')
            ->join('front_users as u', 'u.id', '=', 'v.front_user_id')
            ->leftJoin('business_account_additional_data as ba', 'ba.front_user_id', '=', 'u.id')
            ->leftJoin('generic_key_values_description as video_type_description', 'video_type_description.value_id', '=', 'v.video_type')
            ->leftJoin('site_languages as video_type_language', 'video_type_description.language_id', '=', 'video_type_language.id')
            ->leftJoin(DB::raw('
                (SELECT f.following_id, COUNT(f.follower_id) as followers_count
                FROM followers f
                JOIN front_users fu ON fu.id = f.follower_id
                WHERE fu.is_soft_delete = 0
                GROUP BY f.following_id) as followers
            '), 'followers.following_id', '=', 'u.id')
            ->leftJoin(DB::raw('
                (SELECT f.follower_id, COUNT(f.following_id) as following_count
                FROM followers f
                JOIN front_users fu ON fu.id = f.following_id
                WHERE fu.is_soft_delete = 0
                GROUP BY f.follower_id) as following
            '), 'following.follower_id', '=', 'u.id')
            ->leftJoin('subscription_history as sh', 'sh.id', '=', 'u.current_subscription_id')
            ->where(function ($q) {
                $q->where('video_type_language.is_default', 1)
                    ->orWhere('v.video_type', 0);
            })
            ->where('v.status', 1)
            ->where('v.is_soft_delete', 0);

        if (isset($input['search']['value']) && $input['search']['value'] != '') {
            $baseQuery->where('v.title', 'LIKE', '%'.$input['search']['value'].'%');
        }
        if (isset($input['user']) && $input['user'] != '') {
            $baseQuery->where('v.front_user_id', $input['user']);
        }
        if (isset($input['video_type']) && $input['video_type'] != '') {
            $baseQuery->where('v.video_type', $input['video_type']);
        }
        if (isset($input['title']) && $input['title'] != '') {
            $baseQuery->where('v.title', 'LIKE', '%'.$input['title'].'%');
        }
        if (isset($input['tags']) && $input['tags'] != '') {
            $baseQuery->where('v.tags', 'LIKE', '%'.$input['tags'].'%');
        }
        $baseQuery->where(function ($query) {
            $query->whereDate('sh.end_date', '>=', now()->toDateString())->orWhereNull('sh.end_date');
        });

        if ($user) {
            $blocked_users = DB::table('blocked_users')
                ->where('blocked_by', $user->id)
                ->pluck('blocked_user');
            if ($blocked_users->isNotEmpty()) {
                $baseQuery->whereNotIn('v.front_user_id', $blocked_users);
            }
        }

        $normalQuery = clone $baseQuery;
        if (! (isset($input['is_following']) && $input['is_following'] == 1)) {
            if ($country) {
                $normalQuery->where('v.country', $country);
            }
            if (! empty($cities_ids)) {
                $normalQuery->whereIn('v.city', $cities_ids);
            }
        }

        if ($user) {
            $followingIds = DB::table('followers')
                ->where('follower_id', $user->id)
                ->pluck('following_id');

            if (isset($input['is_following']) && $input['is_following'] == 1) {
                $normalQuery->where(function ($q) {
                    $q->where('v.publish_type', 2)->orWhere('v.publish_type', 1);
                });
                $normalQuery->whereIn('v.front_user_id', $followingIds);
            } else {
                $normalQuery->where(function ($q) use ($followingIds) {
                    $q->where('v.publish_type', 2)
                        ->orWhere(function ($iq) use ($followingIds) {
                            $iq->where('v.publish_type', 1)
                                ->whereIn('v.front_user_id', $followingIds);
                        });
                });
            }
        } else {
            $normalQuery->where('v.publish_type', 2);
        }

        $normalQuery->leftJoin('sponsored_videos as sv', function ($join) use ($cities_ids, $input) {
            $join->on('sv.video_id', '=', 'v.id');
            if (! (isset($input['is_following']) && $input['is_following'] == 1)) {
                $this->applySponsoredCityFilter($join, $cities_ids);
            }
        });
        $normalQuery->where(function ($q) {
            $q->where('v.is_sponsored', 0)->whereNull('sv.video_id');
        });

        $sponsoredQuery = clone $baseQuery;
        $sponsoredQuery->join('sponsored_videos as sv', 'sv.video_id', '=', 'v.id')
            ->where('sv.sponsor_type', 1);
        $this->applySponsoredCityFilter($sponsoredQuery, $cities_ids);

        $premiumQuery = clone $baseQuery;
        $premiumQuery->join('sponsored_videos as sv', 'sv.video_id', '=', 'v.id')
            ->where('sv.sponsor_type', 2);
        $this->applySponsoredCityFilter($premiumQuery, $cities_ids);

        return [
            'normal' => $normalQuery,
            'sponsored' => $sponsoredQuery,
            'premium' => $premiumQuery,
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    private function applySponsoredCityFilter($query, array $cities_ids): void
    {
        if (empty($cities_ids)) {
            return;
        }

        $query->where(function ($cityQuery) use ($cities_ids) {
            foreach ($cities_ids as $cityId) {
                $cityQuery->orWhereRaw('FIND_IN_SET(?, sv.cities)', [$cityId]);
            }
        });
    }

    private function applySeedOrder($query, int $feedSeed)
    {
        return $query
            ->orderByRaw('CRC32(CONCAT(v.id, ?)) ASC', [(string) $feedSeed])
            ->orderBy('v.created_at', 'desc');
    }

    private function fetchVideos($query, int $feedSeed, int $offset = 0, ?int $limit = null)
    {
        $query = $this->applySeedOrder(clone $query, $feedSeed);
        if ($limit !== null) {
            $query->offset($offset)->limit($limit);
        }

        return $query->select(self::videoSelectColumns())->get()->all();
    }

    /**
     * @param  array<int, object>  $normalVideos
     * @param  array<int, object>  $sponsoredVideos
     * @param  array<int, object>  $premiumSponsoredVideos
     * @return array{videos: array, premium_index: int, sponsored_index: int, pattern_index: int, consumed_normals: int}
     */
    private function mergeVideos(
        array $normalVideos,
        array $sponsoredVideos,
        array $premiumSponsoredVideos,
        int $premiumIndex,
        int $sponsoredIndex,
        int $patternIndex,
        int $maxItems
    ): array {
        $finalList = [];
        $normalIndex = 0;
        $totalNormal = count($normalVideos);
        $totalPremium = count($premiumSponsoredVideos);
        $totalNormalSponsored = count($sponsoredVideos);

        $pattern = [];
        if ($totalPremium > 0 && $totalNormalSponsored > 0) {
            $pattern = ['P', 'P', 'S'];
        } elseif ($totalPremium > 0) {
            $pattern = ['P'];
        } elseif ($totalNormalSponsored > 0) {
            $pattern = ['S'];
        }

        $consumedNormals = 0;

        while ($normalIndex < $totalNormal && count($finalList) < $maxItems) {
            for ($j = 0; $j < self::NORMAL_BATCH && $normalIndex < $totalNormal && count($finalList) < $maxItems; $j++, $normalIndex++) {
                $finalList[] = $normalVideos[$normalIndex];
                $consumedNormals++;
            }

            if (! empty($pattern) && count($finalList) < $maxItems) {
                $type = $pattern[$patternIndex % count($pattern)];

                if ($type === 'P' && $totalPremium > 0) {
                    $finalList[] = $premiumSponsoredVideos[$premiumIndex % $totalPremium];
                    $premiumIndex++;
                } elseif ($type === 'S' && $totalNormalSponsored > 0) {
                    $finalList[] = $sponsoredVideos[$sponsoredIndex % $totalNormalSponsored];
                    $sponsoredIndex++;
                }

                $patternIndex = ($patternIndex + 1) % count($pattern);
            }
        }

        return [
            'videos' => $finalList,
            'premium_index' => $premiumIndex,
            'sponsored_index' => $sponsoredIndex,
            'pattern_index' => $patternIndex,
            'consumed_normals' => $consumedNormals,
        ];
    }
}
