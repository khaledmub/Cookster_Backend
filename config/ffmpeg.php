<?php

return [

    'ffmpeg.binaries' => env('FFMPEG_BINARIES', '/usr/bin/ffmpeg'),
    'ffprobe.binaries' => env('FFPROBE_BINARIES', '/usr/bin/ffprobe'),
    'timeout' => (int) env('FFMPEG_TIMEOUT', 7200),
    'hls_segment_seconds' => (int) env('FFMPEG_HLS_SEGMENT_SECONDS', 3),
    'preset' => env('FFMPEG_PRESET', 'veryfast'),
    'threads' => (int) env('FFMPEG_THREADS', 0),
    'max_ladder_height' => (int) env('FFMPEG_MAX_LADDER_HEIGHT', 1080),

    // MP4 fallbacks encoded separately; HLS carries 720/1080. Default 360 only = fast instant start.
    'mp4_ladder_heights' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('FFMPEG_MP4_LADDER_HEIGHTS', '360'))
    ))),

];
