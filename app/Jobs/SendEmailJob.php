<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $jobUuid;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 120;

    public function __construct(
        public string $toEmail,
        public string $subject,
        public string $html,
        public ?string $fromEmail = null,
        public ?string $fromName = null,
    ) {
        $this->jobUuid = (string) Str::orderedUuid();
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $fromEmail = $this->fromEmail ?: config('mail.from.address');
        $fromName = $this->fromName ?: config('mail.from.name');

        Mail::send([], [], function ($message) use ($fromEmail, $fromName) {
            $message->to($this->toEmail)
                ->subject($this->subject)
                ->from($fromEmail, $fromName)
                ->html($this->html);
        });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Queued email failed', [
            'job_uuid' => $this->jobUuid,
            'to_email' => $this->toEmail,
            'subject' => $this->subject,
            'error_code' => (string) $exception->getCode(),
            'error_message' => $exception->getMessage(),
        ]);
    }
}
