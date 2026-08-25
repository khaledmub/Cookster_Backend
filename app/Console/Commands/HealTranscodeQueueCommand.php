<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class HealTranscodeQueueCommand extends Command
{
    protected $signature = 'queue:heal-transcode {--dispatch=50 : Jobs to enqueue when the queue is empty}';

    protected $description = 'Clear zombie transcode queue entries and top up pending work';

    public function handle(): int
    {
        if (! Schema::hasColumn('videos', 'transcode_status')) {
            return self::SUCCESS;
        }

        $queueKey = 'queues:video-processing';
        $reservedKey = $queueKey.':reserved';

        $queued = (int) Redis::llen($queueKey);
        $reserved = (int) Redis::zcard($reservedKey);

        // Reserved entries used to leak because the phpredis serializer broke the queue's
        // ZREM, and this command deleted them wholesale. That also discarded transcodes
        // that were still legitimately running, so it now only reports.
        if ($reserved > 10) {
            $this->warn("{$reserved} transcode jobs reserved; check they are draining");
        }

        if ($queued > 150) {
            $this->warn("Queue backlog is large ({$queued} jobs waiting)");
        }

        if ($queued < 10) {
            $limit = max(1, (int) $this->option('dispatch'));
            $this->call('videos:backfill-media', [
                '--transcode' => true,
                '--limit' => $limit,
            ]);
        } else {
            $this->info("Queue healthy ({$queued} jobs waiting)");
        }

        return self::SUCCESS;
    }
}
