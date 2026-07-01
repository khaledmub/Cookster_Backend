<?php

namespace Tests\Unit;

use App\Support\FeedSocialCache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NearMeGeoScopeTest extends TestCase
{
    #[Test]
    public function riyadh_and_jeddah_resolve_same_saudi_country(): void
    {
        $riyadhCountry = FeedSocialCache::countryIdFromCoords(24.7136, 46.6753);
        $jeddahCountry = FeedSocialCache::countryIdFromCoords(21.4858, 39.1925);

        $this->assertGreaterThan(0, $riyadhCountry);
        $this->assertSame($riyadhCountry, $jeddahCountry);
    }

    #[Test]
    public function local_radius_is_narrower_than_country_video_pool(): void
    {
        $lat = 24.7136;
        $lng = 46.6753;

        $localIds = FeedSocialCache::localCityIds($lat, $lng, 50);
        $countryId = FeedSocialCache::countryIdFromCoords($lat, $lng);

        $this->assertNotEmpty($localIds);

        $countryVideoCount = \Illuminate\Support\Facades\DB::table('videos')
            ->where('status', 1)
            ->where('is_soft_delete', 0)
            ->where('country', $countryId)
            ->count();

        $localVideoCount = \Illuminate\Support\Facades\DB::table('videos')
            ->where('status', 1)
            ->where('is_soft_delete', 0)
            ->whereIn('city', $localIds)
            ->count();

        $this->assertGreaterThanOrEqual($localVideoCount, $countryVideoCount);
    }
}
