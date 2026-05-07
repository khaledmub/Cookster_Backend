<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Aws\SecretsManager\SecretsManagerClient;

class AwsSecretsProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        if(env('APP_ENV') == 'production'){
            $client = new SecretsManagerClient([
                'region' => env('AWS_DEFAULT_REGION', 'me-central-1'),
                'version' => 'latest'
            ]);

            try {
                $rds_result = $client->getSecretValue([
                    'SecretId' => 'cooksterrds'
                ]);

                if (isset($rds_result['SecretString'])) {
                    $secrets = json_decode($rds_result['SecretString'], true);

                    // Dynamically override config values
                    config([
                        'database.connections.mysql.host'     => $secrets['host'] ?? '',
                        'database.connections.mysql.port'     => $secrets['port'] ?? '',
                        'database.connections.mysql.username' => $secrets['username'] ?? '',
                        'database.connections.mysql.password' => $secrets['password'] ?? ''
                    ]);

                    config([
                        'services.rds.username' => $secrets['username'] ?? '',
                        'services.rds.password' => $secrets['password'] ?? '',
                        'services.rds.host'     => $secrets['host'] ?? '',
                        'services.rds.port'     => $secrets['port'] ?? ''
                    ]);
                }

                $s3_result = $client->getSecretValue([
                    'SecretId' => 'cooksterS3bucket'
                ]);

                if (isset($s3_result['SecretString'])) {
                    $secrets = json_decode($s3_result['SecretString'], true);

                    // Keys + bucket/region; bucket must never be null (Flysystem AwsS3V3Adapter type-hints string).
                    $bucket = $secrets['AWS_BUCKET'] ?? $secrets['bucket'] ?? config('services.s3.bucket');
                    $bucket = is_string($bucket) ? $bucket : '';
                    config([
                        'services.s3.key' => $secrets['AWS_ACCESS_KEY_ID'] ?? config('services.s3.key'),
                        'services.s3.secret' => $secrets['AWS_SECRET_ACCESS_KEY'] ?? config('services.s3.secret'),
                        'services.s3.bucket' => $bucket,
                        'services.s3.region' => $secrets['AWS_DEFAULT_REGION'] ?? $secrets['region'] ?? config('services.s3.region'),
                        'services.s3.endpoint' => $secrets['AWS_ENDPOINT'] ?? $secrets['endpoint'] ?? config('services.s3.endpoint'),
                        'services.s3.url' => $secrets['AWS_URL'] ?? $secrets['public_url'] ?? config('services.s3.url'),
                    ]);

                    config([
                        'filesystems.disks.s3.key' => $secrets['AWS_ACCESS_KEY_ID'] ?? config('filesystems.disks.s3.key'),
                        'filesystems.disks.s3.secret' => $secrets['AWS_SECRET_ACCESS_KEY'] ?? config('filesystems.disks.s3.secret'),
                        'filesystems.disks.s3.bucket' => $bucket,
                        'filesystems.disks.s3.region' => $secrets['AWS_DEFAULT_REGION'] ?? $secrets['region'] ?? config('filesystems.disks.s3.region'),
                        'filesystems.disks.s3.endpoint' => $secrets['AWS_ENDPOINT'] ?? $secrets['endpoint'] ?? config('filesystems.disks.s3.endpoint'),
                        'filesystems.disks.s3.url' => $secrets['AWS_URL'] ?? $secrets['public_url'] ?? config('filesystems.disks.s3.url'),
                        'filesystems.disks.s3.cloudfront_path' => $secrets['AWS_CLOUD_FRONT_PATH'] ?? $secrets['cloudfront_path'] ?? config('filesystems.disks.s3.cloudfront_path'),
                    ]);
                }
            } catch (\Exception $e) {
                // Log or handle error (but don’t crash)
                logger()->error('Failed to fetch AWS secrets: ' . $e->getMessage());
            }
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
