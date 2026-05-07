<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | S3 / object storage (used by config/filesystems.php disk "s3" and AwsSecretsProvider in production).
    */
    's3' => [
        'key' => env('AWS_ACCESS_KEY_ID') ?? '',
        'secret' => env('AWS_SECRET_ACCESS_KEY') ?? '',
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        // Must stay a string (never null) so Flysystem’s AwsS3V3Adapter can construct when the disk is resolved.
        'bucket' => env('AWS_BUCKET', ''),
        'endpoint' => env('AWS_ENDPOINT') ?? '',
        // Public asset base URL (e.g. GCS: https://storage.googleapis.com/your-bucket-name)
        'url' => env('AWS_URL'),
    ],

    'urway' => [
        'merchant_key' => env('URWAY_MERCHANT_KEY'),
        'terminal_id' => env('URWAY_TERMINAL_ID'),
        'terminal_pass' => env('URWAY_TERMINAL_PASS'),
        'request_url' => env('URWAY_REQUEST_URL'),
    ],
];
