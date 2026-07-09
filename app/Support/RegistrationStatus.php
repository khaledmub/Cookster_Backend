<?php

namespace App\Support;

final class RegistrationStatus
{
    public const ACTIVE = 'active';

    public const PENDING_VERIFICATION = 'pending_verification';

    public const EXPIRED = 'expired';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ACTIVE,
            self::PENDING_VERIFICATION,
            self::EXPIRED,
        ];
    }

    public static function isActive(?string $status): bool
    {
        return ($status ?? self::ACTIVE) === self::ACTIVE;
    }

    public static function isPending(?string $status): bool
    {
        return ($status ?? '') === self::PENDING_VERIFICATION;
    }
}
