<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CacheHealthCommand extends Command
{
    protected $signature = 'cookster:cache-health {--refresh : Re-probe Redis ignoring the cached status file}';

    protected $description = 'Show Cookster cache/queue/session driver status and Redis reachability.';

    public function handle(): int
    {
        $statusFile = storage_path('framework/cache/cookster_redis_status');

        if ($this->option('refresh') && is_file($statusFile)) {
            @unlink($statusFile);
        }

        $desired = [
            'cache.default' => config('cache.default'),
            'queue.default' => config('queue.default'),
            'session.driver' => config('session.driver'),
        ];

        $redisReachable = $this->probeRedis();
        $statusFromFile = is_file($statusFile) ? (string) @file_get_contents($statusFile) : 'unknown';

        $this->components->info('Cookster cache health');
        $this->table(
            ['Setting', 'Value'],
            [
                ['cache.default (effective)', $desired['cache.default']],
                ['queue.default (effective)', $desired['queue.default']],
                ['session.driver (effective)', $desired['session.driver']],
                ['REDIS_HOST', (string) (config('database.redis.default.host') ?? '')],
                ['REDIS_PORT', (string) (config('database.redis.default.port') ?? '')],
                ['Redis reachable now', $redisReachable ? 'YES' : 'NO'],
                ['Last cached status', $statusFromFile],
                ['Status file', $statusFile],
            ]
        );

        try {
            $key = 'cookster:cache:health:probe';
            Cache::put($key, 'ok', 30);
            $roundtrip = Cache::get($key) === 'ok';
            Cache::forget($key);
            $this->components->info('Cache facade round-trip: '.($roundtrip ? 'OK' : 'FAILED'));
        } catch (\Throwable $e) {
            $this->components->error('Cache facade failed: '.$e->getMessage());
        }

        return self::SUCCESS;
    }

    private function probeRedis(): bool
    {
        if (! extension_loaded('redis')) {
            return false;
        }

        try {
            $host = (string) (config('database.redis.default.host') ?: env('REDIS_HOST', '127.0.0.1'));
            $port = (int) (config('database.redis.default.port') ?: env('REDIS_PORT', 6379));
            $pw = (string) (config('database.redis.default.password') ?: env('REDIS_PASSWORD', ''));

            $redis = new \Redis();
            if (! @$redis->connect($host, $port, 0.5)) {
                return false;
            }
            if ($pw !== '' && strtolower($pw) !== 'null') {
                if (! @$redis->auth($pw)) {
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
}
