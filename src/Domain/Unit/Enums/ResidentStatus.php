<?php

declare(strict_types=1);

namespace Domain\Unit\Enums;

enum ResidentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Invited = 'invited';

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function canLogin(): bool
    {
        return $this === self::Active;
    }
}
