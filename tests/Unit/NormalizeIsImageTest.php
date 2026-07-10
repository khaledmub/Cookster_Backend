<?php

namespace Tests\Unit;

use App\Helpers\AppHelper;
use Tests\TestCase;

class NormalizeIsImageTest extends TestCase
{
    public function test_explicit_photo_flag_wins_even_when_mp4_filename_exists(): void
    {
        $row = (object) [
            'is_image' => 1,
            'video' => '175680257160941.mp4',
            'image' => '17568025717412.jpg',
            'transcode_status' => 'ready',
        ];

        $this->assertSame(1, AppHelper::normalizeIsImage($row));
    }

    public function test_mp4_without_photo_flag_is_video(): void
    {
        $row = (object) [
            'is_image' => 0,
            'video' => '175680257160941.mp4',
            'image' => '17568025717412.jpg',
            'transcode_status' => 'ready',
        ];

        $this->assertSame(0, AppHelper::normalizeIsImage($row));
    }

    public function test_jpg_only_post_is_photo(): void
    {
        $row = (object) [
            'is_image' => 1,
            'video' => '17801611856759.jpg',
            'image' => '17801611856759.jpg',
        ];

        $this->assertSame(1, AppHelper::normalizeIsImage($row));
    }
}
