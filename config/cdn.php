<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public CDN base URL (unsigned)
    |--------------------------------------------------------------------------
    |
    | Used for reels feed URLs when CloudFront signing is disabled.
    | Falls back to S3/GCS public URL when empty (see CdnService).
    |
    */
    'base_url' => rtrim((string) (env('CDN_URL', env('AWS_CLOUD_FRONT_PATH', ''))), '/'),

    /*
    |--------------------------------------------------------------------------
    | Serve media through CDN
    |--------------------------------------------------------------------------
    |
    | Defaults to true when CDN_URL is set. Set CDN_ENABLED=false to force GCS only.
    |
    */
    'enabled' => filter_var(
        env('CDN_ENABLED', env('CDN_URL') ? 'true' : 'false'),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    |--------------------------------------------------------------------------
    | Force direct object-storage URLs (ignore CDN_URL)
    |--------------------------------------------------------------------------
    */
    'force_direct_storage' => filter_var(env('CDN_FORCE_DIRECT_STORAGE', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Include direct GCS URLs alongside CDN URLs (performance A/B on test/prod)
    |--------------------------------------------------------------------------
    */
    'expose_direct_urls' => filter_var(env('CDN_EXPOSE_DIRECT_URLS', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Amazon CloudFront signed URLs
    |--------------------------------------------------------------------------
    */
    'cloudfront' => [
        'enabled' => filter_var(env('CLOUDFRONT_SIGNED_URLS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'domain' => rtrim((string) env('CLOUDFRONT_DOMAIN', ''), '/'),
        'key_pair_id' => env('CLOUDFRONT_KEY_PAIR_ID'),
        'private_key_path' => env('CLOUDFRONT_PRIVATE_KEY_PATH', storage_path('app/cloudfront/private.pem')),
        'url_ttl' => (int) env('CLOUDFRONT_URL_TTL', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Presign endpoint cache (seconds)
    |--------------------------------------------------------------------------
    */
    'presign_cache_ttl' => (int) env('CDN_PRESIGN_CACHE_TTL', 82800),

];
