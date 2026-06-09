<?php

namespace App\Providers;

use App\Services\CdnService;
use Aws\CommandInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Callable for S3 / GCS PutObject & multipart initiate (must be serializable for `php artisan config:cache`).
     *
     * @param  \Aws\CommandInterface|mixed  $command
     */
    public static function stripLegacyAclFromS3Command(mixed $command): void
    {
        if ($command instanceof CommandInterface && $command->offsetExists('ACL')) {
            $command->offsetUnset('ACL');
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CdnService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (env('APP_ENV') !== 'local') {
            URL::forceScheme('https');
        }

        $this->configureS3DiskForUniformBucketAccess();
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('reels-presign', function (Request $request) {
            return Limit::perMinute(60)->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }

    /**
     * Google Cloud Storage with uniform bucket-level access rejects per-object ACLs
     * ("Cannot insert legacy ACL for an object..."). Strip ACL from PutObject and
     * CreateMultipartUpload when using the GCS S3 endpoint, unless S3_USE_OBJECT_ACL=true.
     */
    private function configureS3DiskForUniformBucketAccess(): void
    {
        // Never use env() here: when `php artisan config:cache` runs, env() is null outside
        // config files and GCS uploads fail with "Cannot insert legacy ACL...".
        $explicitRaw = config('filesystems.disks.s3.use_object_acl');
        $explicit = $explicitRaw === null ? null : filter_var($explicitRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($explicit === true) {
            return;
        }

        $endpoint = strtolower((string) (config('filesystems.disks.s3.endpoint') ?? ''));
        $publicUrl = strtolower((string) (config('filesystems.disks.s3.url') ?? ''));
        $isGcs = ($endpoint !== '' && str_contains($endpoint, 'googleapis.com'))
            || str_contains($publicUrl, 'googleapis.com')
            || str_contains($publicUrl, 'storage.cloud.google.com');

        $shouldStrip = ($explicit === false)
            || ($explicit === null && $isGcs);

        if (! $shouldStrip) {
            return;
        }

        // Array callable — never use Closures here: config:cache uses var_export() and breaks on Closure::__set_state().
        $strip = [self::class, 'stripLegacyAclFromS3Command'];

        // Flysystem's AwsS3V3Adapter forwards before_upload but NOT before_initiate (see
        // MUP_AVAILABLE_OPTIONS in league/flysystem-aws-s3-v3). Files >= 16MB use multipart
        // upload, which still sends ACL on CreateMultipartUpload and GCS rejects it.
        // Force single PutObject uploads (up to 5GB) where before_upload strips ACL.
        $opts = array_merge(config('filesystems.disks.s3.options', []), [
            'before_upload' => $strip,
            'before_initiate' => $strip,
            'mup_threshold' => 5 * 1024 * 1024 * 1024,
        ]);

        config(['filesystems.disks.s3.options' => $opts]);
    }
}
