<?php

namespace Tests\Unit;

use App\Services\VideoEncodeFilters;
use App\Services\VideoProbeService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VideoEncodeContractTest extends TestCase
{
    public function test_ladder_scale_filter_forces_even_height_and_yuv420p(): void
    {
        $this->assertSame(
            'scale=-2:720:flags=lanczos,setsar=1,format=yuv420p',
            VideoEncodeFilters::ladderScaleFilter(720)
        );
        $this->assertSame(
            'scale=-2:1080:flags=lanczos,setsar=1,format=yuv420p',
            VideoEncodeFilters::ladderScaleFilter(1081) // snaps odd → even
        );
    }

    public function test_poster_scale_filter_snaps_even_axes(): void
    {
        $filter = VideoEncodeFilters::posterScaleFilter(720);
        $this->assertStringContainsString('force_original_aspect_ratio=decrease', $filter);
        $this->assertStringContainsString('trunc(iw/2)*2:trunc(ih/2)*2', $filter);
    }

    #[DataProvider('ladderHeightProvider')]
    public function test_mp4_ladder_always_includes_720(?int $sourceHeight, array $expected): void
    {
        $probe = new VideoProbeService;
        $this->assertSame($expected, $probe->mp4LadderHeightsForSource($sourceHeight));
    }

    /**
     * @return array<string, array{0: ?int, 1: list<int>}>
     */
    public static function ladderHeightProvider(): array
    {
        return [
            'unknown source → full ladder' => [null, [360, 720, 1080]],
            '480p still gets 720' => [480, [360, 720]],
            '720p still gets 720, not 1080' => [720, [360, 720]],
            '1080p gets full ladder' => [1080, [360, 720, 1080]],
            '360p still gets 720' => [360, [360, 720]],
        ];
    }
}
