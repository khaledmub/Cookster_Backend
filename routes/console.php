<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('queue:restart')->dailyAt('04:00');

// Backlog alarm: a queue that stops draining fires QueueBusy (see AppServiceProvider).
Schedule::command('queue:monitor redis:thumbnails,redis:notifications,redis:emails,redis:default,redis:video-processing --max=250')
    ->everyFiveMinutes()
    ->onOneServer();

Schedule::command('transcode:status-report')
    ->hourly()
    ->when(fn () => (bool) env('TRANSCODE_STATUS_EMAIL'))
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/transcode-status-report.log'));

// Top up the transcode queue when it runs dry.
Schedule::command('queue:heal-transcode --dispatch=50')
    ->everyFiveMinutes()
    ->withoutOverlapping(4)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/transcode-heal.log'));

Schedule::command('registrations:expire-pending')
    ->dailyAt('03:30')
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/expire-pending-registrations.log'));

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

