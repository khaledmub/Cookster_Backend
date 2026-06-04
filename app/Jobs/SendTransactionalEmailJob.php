<?php

namespace App\Jobs;

use App\Helpers\AppHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTransactionalEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public ?string $fromEmail,
        public string $toEmail,
        public string $subject,
        public string $htmlBody,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        AppHelper::sendEmailNow($this->fromEmail, $this->toEmail, $this->subject, $this->htmlBody);
    }
}
