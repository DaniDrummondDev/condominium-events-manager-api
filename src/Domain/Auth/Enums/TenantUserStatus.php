<?php

declare(strict_types=1);

namespace Domain\Auth\Enums;

enum TenantUserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Invited = 'invited';
    case Blocked = 'blocked';

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function canLogin(): bool
    {
        return $this === self::Active;
    }
}
