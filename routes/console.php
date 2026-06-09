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

