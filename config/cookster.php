<?php

/**
 * Cookster-specific configuration. All `env()` calls live here so values
 * are baked into the cached config bundle when running `php artisan config:cache`
 * in production. Reading env() inside providers/blades/controllers does NOT
 * work after config:cache — always go through config() for these settings.
 */
return [

    'cache' => [
        // Driver to use when Redis is configured (CACHE_STORE=redis) but unreachable.
        'fallback_store' => env('CACHE_FALLBACK_STORE', 'file'),
    ],

    'queue' => [
        // Connection to use when QUEUE_CONNECTION=redis but Redis is unreachable.
        'fallback_connection' => env('QUEUE_FALLBACK_CONNECTION', 'database'),
    ],

    'session' => [
        // Driver to use when SESSION_DRIVER=redis but Redis is unreachable.
        'fallback_driver' => env('SESSION_FALLBACK_DRIVER', 'database'),
    ],

    'redis_probe' => [
        // Seconds to memoize the Redis health probe result on disk so we don't
        // round-trip Redis on every request when it is down (or up).
        'ttl' => (int) env('REDIS_PROBE_TTL', 60),
        // Socket connect timeout for the health probe (seconds).
        'timeout' => (float) env('REDIS_PROBE_TIMEOUT', 0.5),
    ],

    // Formatting strings used across notifications/emails. Centralised so they
    // survive config:cache (env() returns null inside cached-config requests).
    'formats' => [
        'date_time' => env('DATE_TIME_FORMAT', 'd-M-Y h:i A'),
    ],

    'deeplink' => [
        'android_package' => env('ANDROID_APP_PACKAGE', 'com.cookster.cooksterapp'),
        'android_sha256' => env('ANDROID_APP_SHA256', '29:EA:D4:28:4B:EB:5A:11:DC:F7:F9:C0:25:CC:F5:36:63:72:4C:C3:85:7D:6A:5A:9E:EB:04:E5:29:2D:80:FF'),
        'ios_team_id' => env('IOS_TEAM_ID', 'Y3972XUW7F'),
        'ios_bundle_id' => env('IOS_APP_BUNDLE_ID', 'com.cookster.cooksterapp'),
        // Custom-scheme host used by app shares (cookster://open.cookster.app/...).
        'app_scheme_host' => env('APP_SCHEME_HOST', 'open.cookster.app'),
    ],

];
