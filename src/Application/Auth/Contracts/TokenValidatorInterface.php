<?php

declare(strict_types=1);

namespace Application\Auth\Contracts;

use Domain\Auth\ValueObjects\TokenClaims;

interface TokenValidatorInterface
{
    /**
     * Validates a JWT string and returns the parsed claims.
     *
     * @throws \Domain\Auth\Exceptions\AuthenticationException on invalid/expired/revoked token
     */
    public function validate(string $jwt): TokenClaims;
}
