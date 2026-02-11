<?php

declare(strict_types=1);

namespace Application\Auth\UseCases;

use Application\Auth\Contracts\TenantRefreshTokenRepositoryInterface;
use Application\Auth\Contracts\TokenRevocationInterface;
use Domain\Auth\ValueObjects\TokenClaims;

final readonly class TenantLogout
{
    public function __construct(
        private TokenRevocationInterface $tokenRevocation,
        private TenantRefreshTokenRepositoryInterface $refreshTokenRepository,
    ) {}

    public function execute(TokenClaims $claims): void
    {
        // Revoke the current access token
        $this->tokenRevocation->revoke($claims->jti, $claims->expiresAt);

        // Revoke all refresh tokens for this user in the tenant DB
        $this->refreshTokenRepository->revokeAllForUser($claims->sub);
    }
}
