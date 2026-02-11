<?php

declare(strict_types=1);

namespace Domain\Auth\ValueObjects;

enum TokenType: string
{
    case Access = 'access';
    case MfaRequired = 'mfa_required';

    public function prefix(): string
    {
        return match ($this) {
            self::Access => 'tok_',
            self::MfaRequired => 'mfa_',
        };
    }

    public function ttlSeconds(): int
    {
        return match ($this) {
            self::Access => 900,       // 15 minutes
            self::MfaRequired => 300,  // 5 minutes
        };
    }
}
