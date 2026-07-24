<?php

namespace App\Services;

/**
 * Shared ffmpeg scale/pixel-format filters for ladder encodes and posters.
 *
 * MediaCodec / Honor surfaces stall when width or height is odd (yuv420p).
 * Ladder tiers use an exact even height; width is forced even via -2.
 */
class VideoEncodeFilters
{
    /**
     * Scale to an exact ladder height with even width, square pixels, yuv420p.
     */
    public static function ladderScaleFilter(int $height): string
    {
        $height = max(2, (int) (round($height / 2) * 2));

        return 'scale=-2:'.$height.':flags=lanczos,setsar=1,format=yuv420p';
    }

    /**
     * Sharp feed poster: ~720px on the long edge, even dimensions.
     */
    public static function posterScaleFilter(int $maxEdge = 720): string
    {
        $maxEdge = max(2, (int) (round($maxEdge / 2) * 2));

        // Long-edge fit, then snap both axes even (yuv420 / WebP friendly).
        return 'scale='.$maxEdge.':'.$maxEdge.':force_original_aspect_ratio=decrease:flags=lanczos,'
            .'scale=trunc(iw/2)*2:trunc(ih/2)*2';
    }
}
