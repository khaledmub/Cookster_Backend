<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // Use env() here — config/services.php loads *after* this file (ksort), so
        // config('services.s3.*') is null during bootstrap and would freeze an empty bucket.
        // Production overrides still apply via AwsSecretsProvider (filesystems.disks.s3.*).
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID') ?? '',
            'secret' => env('AWS_SECRET_ACCESS_KEY') ?? '',
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => (string) (env('AWS_BUCKET') ?? ''),
            'url' => env('AWS_URL'),
            // Optional CDN base; AppHelper::mediaPublicBaseUrl() prefers this over url (must use config(), not env() in helpers).
            'cloudfront_path' => env('AWS_CLOUD_FRONT_PATH'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            // Read in AppServiceProvider via config() so config:cache does not break GCS ACL stripping.
            'use_object_acl' => env('S3_USE_OBJECT_ACL'),
            // Default string so filter_var is true when .env omits the key.
            'api_media_absolute_urls' => filter_var(env('API_MEDIA_USE_ABSOLUTE_VIDEO_URL', 'true'), FILTER_VALIDATE_BOOLEAN),
            'throw' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
