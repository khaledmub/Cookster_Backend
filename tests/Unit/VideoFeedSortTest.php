<?php

namespace Tests\Unit;

use App\Support\ApiTimestamp;
use App\Support\VideoFeedSort;
use Illuminate\Http\Request;
use Tests\TestCase;

class VideoFeedSortTest extends TestCase
{
    public function test_resolve_defaults_to_newest(): void
    {
        $this->assertSame(VideoFeedSort::NEWEST, VideoFeedSort::resolve(null));
        $this->assertSame(VideoFeedSort::NEWEST, VideoFeedSort::resolve('invalid'));
        $this->assertSame(VideoFeedSort::OLDEST, VideoFeedSort::resolve('oldest'));
    }

    public function test_explicit_chronological_requires_sort_by_param(): void
    {
        $request = Request::create('/api/reels', 'GET', ['sort_by' => 'newest']);
        $this->assertTrue(VideoFeedSort::isExplicitChronologicalRequest($request));

        $legacy = Request::create('/api/videos/list', 'POST', ['paginate' => 1]);
        $this->assertFalse(VideoFeedSort::isExplicitChronologicalRequest($legacy));
    }

    public function test_api_timestamp_formats_iso8601_utc(): void
    {
        $formatted = ApiTimestamp::format('2024-12-13 10:57:49');
        $this->assertMatchesRegularExpression(
            '/^2024-12-13T\d{2}:57:49\.000000Z$/',
            (string) $formatted
        );
    }
}
