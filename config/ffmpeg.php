<?php

return [

    'ffmpeg.binaries' => env('FFMPEG_BINARIES', '/usr/bin/ffmpeg'),
    'ffprobe.binaries' => env('FFPROBE_BINARIES', '/usr/bin/ffprobe'),
    'timeout' => (int) env('FFMPEG_TIMEOUT', 7200),
    'hls_segment_seconds' => (int) env('FFMPEG_HLS_SEGMENT_SECONDS', 2),
    'preset' => env('FFMPEG_PRESET', 'fast'),
    'threads' => (int) env('FFMPEG_THREADS', 0),
    'max_ladder_height' => (int) env('FFMPEG_MAX_LADDER_HEIGHT', 1080),
    // GOP length in frames (~2s at 24fps). Short GOP = faster first-frame on Range requests.
    'gop_size' => (int) env('FFMPEG_GOP_SIZE', 48),
    'video_profile' => env('FFMPEG_VIDEO_PROFILE', 'main'),

    // MP4 fallbacks for mobile preload (360 + 720 fast-start). 1080 stays HLS-only.
    'mp4_ladder_heights' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('FFMPEG_MP4_LADDER_HEIGHTS', '360,720'))
    ))),

];
