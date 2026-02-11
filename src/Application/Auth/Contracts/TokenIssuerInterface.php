<?php

declare(strict_types=1);

namespace Application\Auth\Contracts;

use Domain\Auth\ValueObjects\TokenClaims;

interface TokenIssuerInterface
{
    /**
     * Issues a signed JWT from the given claims.
     */
    public function issue(TokenClaims $claims): string;
}
