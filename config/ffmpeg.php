<?php

return [

    'ffmpeg.binaries' => env('FFMPEG_BINARIES', '/usr/bin/ffmpeg'),
    'ffprobe.binaries' => env('FFPROBE_BINARIES', '/usr/bin/ffprobe'),
    'timeout' => (int) env('FFMPEG_TIMEOUT', 7200),
    'hls_segment_seconds' => (int) env('FFMPEG_HLS_SEGMENT_SECONDS', 3),

];
