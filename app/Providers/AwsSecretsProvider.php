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

                    // Dynamically override config values
                    config([
                        'filesystems.disks.s3.key'      => $secrets['AWS_ACCESS_KEY_ID'] ?? '',
                        'filesystems.disks.s3.secret'   => $secrets['AWS_SECRET_ACCESS_KEY'] ?? ''
                    ]);

                    config([
                        'services.s3.key'    => $secrets['AWS_ACCESS_KEY_ID'] ?? '',
                        'services.s3.secret' => $secrets['AWS_SECRET_ACCESS_KEY'] ?? ''
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
