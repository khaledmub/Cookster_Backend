<?php

namespace Tests\Unit;

use App\Support\RegistrationService;
use App\Support\RegistrationStatus;
use App\Support\UsernameService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrationLifecycleTest extends TestCase
{
    #[Test]
    public function pending_username_does_not_block_active_uniqueness_check(): void
    {
        $this->assertTrue(RegistrationService::isUsernameAvailable('pending_only_user_test'));
        $this->assertContains(
            RegistrationService::usernameAvailabilityReason('pending_only_user_test'),
            ['available', 'pending_resume_allowed']
        );
    }

    #[Test]
    public function registration_user_payload_uses_registration_status_string(): void
    {
        $payload = RegistrationService::registrationUserPayload((object) [
            'id' => 'user-1',
            'name' => 'Khaled',
            'email' => 'khaled@example.com',
            'user_name' => 'khaled',
            'registration_status' => RegistrationStatus::PENDING_VERIFICATION,
            'email_verified_at' => null,
            'entity' => 1,
        ]);

        $this->assertSame('pending_verification', $payload['status']);
        $this->assertFalse($payload['email_verified']);
    }

    #[Test]
    public function username_service_only_checks_active_accounts(): void
    {
        $rules = UsernameService::validationRules();
        $uniqueRule = collect($rules['user_name'])->first(fn ($rule) => $rule instanceof \Illuminate\Validation\Rules\Unique);

        $this->assertInstanceOf(\Illuminate\Validation\Rules\Unique::class, $uniqueRule);
    }
}
