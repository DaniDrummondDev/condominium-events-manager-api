<?php

declare(strict_types=1);

namespace Domain\Auth\Exceptions;

use Domain\Shared\Exceptions\DomainException;

class AuthenticationException extends DomainException
{
    public static function invalidCredentials(): self
    {
        return new self(
            'Invalid email or password',
            'INVALID_CREDENTIALS',
        );
    }

    public static function accountDisabled(): self
    {
        return new self(
            'Account is disabled',
            'ACCOUNT_DISABLED',
        );
    }

    public static function accountLocked(int $remainingMinutes): self
    {
        return new self(
            "Account is locked. Try again in {$remainingMinutes} minutes",
            'ACCOUNT_LOCKED',
            ['remaining_minutes' => $remainingMinutes],
        );
    }

    public static function mfaRequired(): self
    {
        return new self(
            'MFA verification is required',
            'MFA_REQUIRED',
        );
    }

    public static function invalidToken(string $reason = 'Token is invalid'): self
    {
        return new self(
            $reason,
            'INVALID_TOKEN',
        );
    }

    public static function tokenExpired(): self
    {
        return new self(
            'Token has expired',
            'TOKEN_EXPIRED',
        );
    }

    public static function tokenRevoked(): self
    {
        return new self(
            'Token has been revoked',
            'TOKEN_REVOKED',
        );
    }
}
