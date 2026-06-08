<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('queue:prune-failed --hours=168')->weekly();
Schedule::command('queue:restart')->dailyAt('04:00');

// Dispatch transcode jobs for videos not yet ready (worker: cookster-transcode.service).
Schedule::command('videos:backfill-media --transcode --limit=60')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/transcode-backfill.log'));
