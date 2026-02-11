<?php

declare(strict_types=1);

namespace Application\Auth\Contracts;

use DateTimeImmutable;
use Domain\Auth\ValueObjects\JtiToken;

interface TokenRevocationInterface
{
    /**
     * Revokes a token by its JTI. The revocation entry auto-expires
     * at the token's original expiration time.
     */
    public function revoke(JtiToken $jti, DateTimeImmutable $expiresAt): void;

    /**
     * Checks if a token has been revoked.
     */
    public function isRevoked(JtiToken $jti): bool;
}
