<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('queue:prune-failed --hours=168')->weekly();
Schedule::command('queue:restart')->dailyAt('04:00');

Schedule::command('transcode:status-report')
    ->hourly()
    ->when(fn () => (bool) env('TRANSCODE_STATUS_EMAIL'))
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/transcode-status-report.log'));

// Self-heal zombie reserved jobs, then top up transcode queue.
Schedule::command('queue:heal-transcode --dispatch=50')
    ->everyFiveMinutes()
    ->withoutOverlapping(4)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/transcode-heal.log'));

// Use idle transcode capacity for ladder upgrades (1080, fast-start gaps).
Schedule::command('videos:backfill-media --upgrade-ladder --limit=5')
    ->everyFifteenMinutes()
    ->when(function () {
        if (! class_exists(\Illuminate\Support\Facades\Redis::class)) {
            return false;
        }
        $waiting = (int) \Illuminate\Support\Facades\Redis::llen('queues:video-processing');
        $reserved = (int) \Illuminate\Support\Facades\Redis::zcard('queues:video-processing:reserved');

        return $waiting === 0 && $reserved === 0;
    })
    ->withoutOverlapping(10)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/idle-ladder-backfill.log'));

