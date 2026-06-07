<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Thin caching wrapper that applies effective techniques on top of Laravel's
 * cache facade and degrades gracefully if the underlying store is unavailable.
 *
 * Techniques used:
 *  - Stale-While-Revalidate (SWR) via Cache::flexible when available, so a
 *    user never pays the full miss latency once an item has been cached.
 *  - Cache stampede protection via atomic locks for expensive recomputes.
 *  - Always falls back to executing the callback if the store throws.
 *
 * Keys should be short and namespaced (e.g. "feed:blocked:{userId}").
 */
class CookCache
{
    /**
     * Cached lookup with optional SWR.
     *
     * @template T
     * @param  string  $key
     * @param  int|array{0:int,1:int}  $ttl  int = fresh TTL seconds; [fresh, stale] = SWR
     * @param  \Closure(): T  $callback
     * @return T
     */
    public static function remember(string $key, int|array $ttl, Closure $callback): mixed
    {
        try {
            $root = Cache::getFacadeRoot();
            if (is_array($ttl) && method_exists($root, 'flexible')) {
                /** @phpstan-ignore-next-line */
                return Cache::flexible($key, $ttl, $callback);
            }

            $fresh = is_array($ttl) ? (int) $ttl[0] : (int) $ttl;
            return Cache::remember($key, $fresh, $callback);
        } catch (\Throwable $e) {
            return $callback();
        }
    }

    /**
     * Same as remember(), but adds an atomic lock to prevent cache stampede
     * when many concurrent requests miss at the same moment (e.g. after key
     * expiry on a hot endpoint).
     */
    public static function rememberLocked(string $key, int|array $ttl, Closure $callback, int $lockSeconds = 5): mixed
    {
        try {
            $root = Cache::getFacadeRoot();
            $fresh = is_array($ttl) ? (int) $ttl[0] : (int) $ttl;

            if (method_exists($root, 'lock')) {
                $lock = Cache::lock("lock:{$key}", $lockSeconds);
                try {
                    if ($lock->get()) {
                        return Cache::remember($key, $fresh, $callback);
                    }

                    // Another worker is computing — return existing value if any,
                    // otherwise run the callback (last-resort, no double recompute storm).
                    $existing = Cache::get($key);
                    if ($existing !== null) {
                        return $existing;
                    }

                    return $callback();
                } finally {
                    optional($lock)->release();
                }
            }

            return self::remember($key, $ttl, $callback);
        } catch (\Throwable $e) {
            return $callback();
        }
    }

    public static function forget(string $key): bool
    {
        try {
            return (bool) Cache::forget($key);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function flush(string $prefix = ''): bool
    {
        try {
            if ($prefix === '') {
                return (bool) Cache::flush();
            }

            $root = Cache::getFacadeRoot();
            if (method_exists($root, 'tags')) {
                return (bool) Cache::tags([$prefix])->flush();
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
