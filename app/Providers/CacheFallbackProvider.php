<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Detects Redis availability at boot and transparently falls back to the
 * configured non-Redis driver (file/database) for cache, queue, and session
 * when Redis is unreachable.
 *
 * The health probe result is memoized in a tiny status file for HEALTH_TTL
 * seconds so we don't pay a TCP round-trip on every request when Redis is
 * down (or up).
 *
 * Driven by .env:
 *   CACHE_STORE              (e.g. redis)
 *   QUEUE_CONNECTION         (e.g. redis)
 *   SESSION_DRIVER           (e.g. redis)
 *   CACHE_FALLBACK_STORE     (default: file)
 *   QUEUE_FALLBACK_CONNECTION(default: database)
 *   SESSION_FALLBACK_DRIVER  (default: database)
 *   REDIS_HOST / REDIS_PORT / REDIS_PASSWORD
 */
class CacheFallbackProvider extends ServiceProvider
{
    private const HEALTH_TTL = 60;

    private const PROBE_TIMEOUT_SECONDS = 0.5;

    /**
     * Becomes true only when isRedisAvailable() performed a fresh probe this
     * request (i.e., the status file was not cached). Used to throttle the
     * fallback log to at most once per HEALTH_TTL window.
     */
    private bool $probedThisRequest = false;

    public function register(): void
    {
        // CRITICAL: do NOT mutate config during `php artisan config:cache`,
        // otherwise the swapped fallback value would be baked into the cached
        // config bundle permanently and Redis recovery would never be observed.
        if ($this->isConfigCachingCommand()) {
            return;
        }

        if (! $this->wantsRedis()) {
            return;
        }

        if ($this->isRedisAvailable()) {
            return;
        }

        // Read fallbacks via config first so they survive `php artisan config:cache`
        // (env() returns null in providers when config is cached).
        $cacheFallback = (string) (config('cookster.cache.fallback_store') ?: env('CACHE_FALLBACK_STORE', 'file'));
        $queueFallback = (string) (config('cookster.queue.fallback_connection') ?: env('QUEUE_FALLBACK_CONNECTION', 'database'));
        $sessionFallback = (string) (config('cookster.session.fallback_driver') ?: env('SESSION_FALLBACK_DRIVER', 'database'));

        $applied = [];

        if (config('cache.default') === 'redis') {
            config(['cache.default' => $cacheFallback]);
            $applied['cache'] = $cacheFallback;
        }

        if (config('queue.default') === 'redis') {
            config(['queue.default' => $queueFallback]);
            $applied['queue'] = $queueFallback;
        }

        if (config('session.driver') === 'redis') {
            config(['session.driver' => $sessionFallback]);
            $applied['session'] = $sessionFallback;
        }

        // Only log when the status file was just written this request — avoids
        // spamming the log on every request while Redis is down.
        if (! empty($applied) && $this->probedThisRequest) {
            try {
                Log::warning('[CacheFallback] Redis unreachable; switched to fallback drivers.', $applied);
            } catch (\Throwable $e) {
                // Logging must never break the request.
            }
        }
    }

    public function boot(): void
    {
        // Expose status helper for the cookster:cache-health command.
        $this->app->instance('cookster.cache.redis_status_file', $this->statusFilePath());
    }

    private function wantsRedis(): bool
    {
        return config('cache.default') === 'redis'
            || config('queue.default') === 'redis'
            || config('session.driver') === 'redis';
    }

    private function isRedisAvailable(): bool
    {
        $statusFile = $this->statusFilePath();
        $ttl = (int) (config('cookster.redis_probe.ttl') ?: self::HEALTH_TTL);

        if (is_file($statusFile) && (time() - (int) @filemtime($statusFile)) < $ttl) {
            return @file_get_contents($statusFile) === 'up';
        }

        // Fresh probe — set flag so the caller can log state transitions once.
        $this->probedThisRequest = true;

        $up = $this->probeRedis();

        $dir = dirname($statusFile);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($statusFile, $up ? 'up' : 'down');

        return $up;
    }

    private function probeRedis(): bool
    {
        if (! extension_loaded('redis')) {
            return false;
        }

        // config() values survive `config:cache`; env() does not in providers.
        $host = (string) (config('database.redis.default.host') ?: env('REDIS_HOST', '127.0.0.1'));
        $port = (int) (config('database.redis.default.port') ?: env('REDIS_PORT', 6379));
        $password = (string) (config('database.redis.default.password') ?: env('REDIS_PASSWORD', ''));

        try {
            $redis = new \Redis();
            if (! @$redis->connect($host, $port, self::PROBE_TIMEOUT_SECONDS)) {
                return false;
            }
            if ($password !== '' && strtolower($password) !== 'null') {
                if (! @$redis->auth($password)) {
                    @$redis->close();
                    return false;
                }
            }
            $pong = @$redis->ping();
            @$redis->close();

            return $pong !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function statusFilePath(): string
    {
        return storage_path('framework/cache/cookster_redis_status');
    }

    /**
     * True if the current process is one of the artisan commands that *writes*
     * a config snapshot. Skipping the swap here keeps the cached config faithful
     * to the developer's intent so Redis can be re-enabled by simply starting
     * the Redis service — no manual `config:clear` required.
     */
    private function isConfigCachingCommand(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        $argv = $_SERVER['argv'] ?? [];
        foreach ($argv as $arg) {
            if ($arg === 'config:cache' || $arg === 'optimize') {
                return true;
            }
        }

        return false;
    }
}
