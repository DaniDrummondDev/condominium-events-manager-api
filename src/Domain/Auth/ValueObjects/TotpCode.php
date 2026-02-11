<?php

declare(strict_types=1);

namespace Domain\Auth\ValueObjects;

use InvalidArgumentException;

final readonly class TotpCode
{
    public function __construct(
        private string $code,
    ) {
        if (! preg_match('/^\d{6}$/', $this->code)) {
            throw new InvalidArgumentException('TOTP code must be exactly 6 digits');
        }
    }

    public function value(): string
    {
        return $this->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
