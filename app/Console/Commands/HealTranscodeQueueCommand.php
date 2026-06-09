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

        if ($reserved > 0 && ($queued === 0 || $reserved > 10)) {
            Redis::del($reservedKey);
            $this->info("Cleared {$reserved} zombie reserved transcode jobs");
        }

        $resetFailed = DB::table('videos')
            ->where('transcode_status', 'failed')
            ->update(['transcode_status' => 'pending']);

        if ($resetFailed > 0) {
            $this->info("Reset {$resetFailed} failed videos to pending");
        }

        $queued = (int) Redis::llen($queueKey);

        if ($queued > 150) {
            Redis::del($queueKey, $queueKey.':delayed');
            $this->warn("Purged bloated queue ({$queued} duplicate jobs)");
            $queued = 0;
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
