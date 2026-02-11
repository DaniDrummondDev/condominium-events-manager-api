<?php

declare(strict_types=1);

namespace Domain\Governance\Enums;

enum PenaltyStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Expired, self::Revoked], true);
    }
}
