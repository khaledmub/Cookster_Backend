<?php

namespace Tests\Unit;

use App\Http\Controllers\API\ReelsController;
use App\Support\FeedSocialCache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReelsNearMeGeoTest extends TestCase
{
    #[Test]
    public function near_me_city_ids_returns_city_group_before_country_fallback(): void
    {
        $lat = 29.97;
        $lng = 30.94;

        $nearMe = FeedSocialCache::nearMeCityIds($lat, $lng);

        $this->assertNotEmpty($nearMe['cities_ids']);
        $this->assertGreaterThan(0, $nearMe['city']);
        $this->assertArrayHasKey('expanded', $nearMe);
        $this->assertArrayHasKey('hash', $nearMe);
    }

    #[Test]
    public function reels_controller_defaults_to_city_scope_without_scope_param(): void
    {
        $controller = new ReelsController;
        $method = new \ReflectionMethod(ReelsController::class, 'parseGeoScope');
        $method->setAccessible(true);

        $request = \Illuminate\Http\Request::create('/api/reels', 'GET', [
            'feed' => 'near_me',
            'latitude' => 29.97,
            'longitude' => 30.94,
        ]);

        $this->assertSame('city', $method->invoke($controller, $request));
    }

    #[Test]
    public function reels_controller_honors_explicit_country_scope(): void
    {
        $controller = new ReelsController;
        $method = new \ReflectionMethod(ReelsController::class, 'parseGeoScope');
        $method->setAccessible(true);

        $request = \Illuminate\Http\Request::create('/api/reels', 'GET', [
            'feed' => 'near_me',
            'latitude' => 29.97,
            'longitude' => 30.94,
            'scope' => 'country',
        ]);

        $this->assertSame('country', $method->invoke($controller, $request));
    }
}
