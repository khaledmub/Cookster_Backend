<?php

namespace App\Console\Commands;

use App\Helpers\AppHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class TranscodeStatusReportCommand extends Command
{
    protected $signature = 'transcode:status-report {--email= : Override recipient email}';

    protected $description = 'Email hourly transcode progress summary';

    public function handle(): int
    {
        if (! Schema::hasColumn('videos', 'transcode_status')) {
            $this->warn('transcode_status column missing');

            return self::SUCCESS;
        }

        $to = $this->option('email') ?: env('TRANSCODE_STATUS_EMAIL');
        if (! $to) {
            $this->error('Set TRANSCODE_STATUS_EMAIL in .env or pass --email=');

            return self::FAILURE;
        }

        $counts = DB::table('videos')
            ->select('transcode_status', DB::raw('count(*) as c'))
            ->groupBy('transcode_status')
            ->pluck('c', 'transcode_status');

        $ready = (int) ($counts['ready'] ?? 0);
        $pending = (int) ($counts['pending'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $total = $ready + $pending + $failed;
        $pct = $total > 0 ? round(($ready / $total) * 100, 1) : 0;

        $queue = (int) Redis::llen('queues:video-processing');
        $reserved = (int) Redis::zcard('queues:video-processing:reserved');
        $host = gethostname() ?: 'unknown';
        $time = now()->format('Y-m-d H:i:s T');

        $subject = sprintf('Cookster transcode: %d/%d ready (%.1f%%)', $ready, $total, $pct);
        $html = <<<HTML
<h2>Transcode status — {$host}</h2>
<p><strong>Time:</strong> {$time}</p>
<table border="1" cellpadding="8" cellspacing="0">
<tr><th>Status</th><th>Count</th></tr>
<tr><td>ready</td><td>{$ready}</td></tr>
<tr><td>pending</td><td>{$pending}</td></tr>
<tr><td>failed</td><td>{$failed}</td></tr>
<tr><td><strong>total</strong></td><td><strong>{$total}</strong></td></tr>
</table>
<p><strong>Progress:</strong> {$pct}%</p>
<p><strong>Queue:</strong> {$queue} waiting, {$reserved} reserved</p>
HTML;

        $result = AppHelper::sendEmailNow(null, $to, $subject, $html);

        if (! ($result['success'] ?? false)) {
            $this->error($result['error_message'] ?? 'Email failed');

            return self::FAILURE;
        }

        $this->info("Sent transcode report to {$to}");

        return self::SUCCESS;
    }
}
