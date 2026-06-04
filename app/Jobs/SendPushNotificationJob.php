<?php

namespace App\Jobs;

use App\Helpers\AppHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $pushNotificationText
     * @param  list<string>  $deviceTokens
     */
    public function __construct(
        public array $pushNotificationText,
        public array $deviceTokens,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        AppHelper::sendPushNotificationNow($this->pushNotificationText, $this->deviceTokens);
    }
}
