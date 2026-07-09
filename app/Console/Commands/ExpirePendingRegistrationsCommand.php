<?php

namespace App\Console\Commands;

use App\Support\RegistrationService;
use Illuminate\Console\Command;

class ExpirePendingRegistrationsCommand extends Command
{
    protected $signature = 'registrations:expire-pending';

    protected $description = 'Expire stale pending_verification accounts and free their email/username';

    public function handle(): int
    {
        $count = RegistrationService::expireStalePendingRegistrations();
        $this->info("Expired {$count} pending registration(s).");

        return self::SUCCESS;
    }
}
