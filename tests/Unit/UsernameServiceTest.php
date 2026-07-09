<?php

namespace Tests\Unit;

use App\Support\UsernameService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsernameServiceTest extends TestCase
{
    #[Test]
    public function normalize_lowercases_and_trims(): void
    {
        $this->assertSame('khaled_1', UsernameService::normalize('  Khaled_1  '));
    }

    #[Test]
    public function format_rules_require_lowercase_alphanumeric_underscore(): void
    {
        $rules = UsernameService::formatRules()['user_name'];

        $this->assertContains('required', $rules);
        $this->assertContains('regex:/^[a-z0-9_]+$/', $rules);
    }

    #[Test]
    public function custom_messages_use_translation_keys(): void
    {
        $messages = UsernameService::customMessages();

        $this->assertSame(__('messages.username_taken'), $messages['user_name.unique']);
    }
}
