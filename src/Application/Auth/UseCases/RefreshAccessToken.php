<?php

declare(strict_types=1);

namespace Application\Auth\UseCases;

use Application\Auth\Contracts\PlatformUserRepositoryInterface;
use Application\Auth\Contracts\RefreshTokenRepositoryInterface;
use Application\Auth\Contracts\TokenIssuerInterface;
use Application\Auth\DTOs\AuthTokensDTO;
use Application\Auth\DTOs\RefreshRequestDTO;
use Application\Auth\DTOs\RefreshTokenRecord;
use Application\Shared\Contracts\EventDispatcherInterface;
use DateTimeImmutable;
use Domain\Auth\Events\TokenRefreshed;
use Domain\Auth\Events\TokenReuseDetected;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Auth\ValueObjects\TokenClaims;
use Domain\Auth\ValueObjects\TokenType;
use Domain\Shared\ValueObjects\Uuid;

final readonly class RefreshAccessToken
{
    public function __construct(
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private PlatformUserRepositoryInterface $userRepository,
        private TokenIssuerInterface $tokenIssuer,
        private EventDispatcherInterface $eventDispatcher,
        private int $refreshTokenTtl = 604800,
    ) {}

    public function execute(RefreshRequestDTO $dto): AuthTokensDTO
    {
        $now = new DateTimeImmutable;
        $tokenHash = hash('sha256', $dto->refreshToken);

        $record = $this->refreshTokenRepository->findByTokenHash($tokenHash);

        if ($record === null) {
            throw AuthenticationException::invalidToken();
        }

        // Reuse detection: if token was already used, revoke entire chain
        if ($record->isUsed()) {
            $this->refreshTokenRepository->revokeChain($record->id);
            $this->eventDispatcher->dispatch(new TokenReuseDetected(
                userId: $record->userId,
                tokenId: $record->id,
                ipAddress: $dto->ipAddress,
            ));

            throw AuthenticationException::invalidToken();
        }

        if ($record->isRevoked()) {
            throw AuthenticationException::invalidToken();
        }

        if ($record->isExpired($now)) {
            throw AuthenticationException::tokenExpired();
        }

        // Verify user is still active
        $user = $this->userRepository->findById($record->userId);

        if ($user === null || ! $user->status()->isActive()) {
            $this->refreshTokenRepository->revokeAllForUser($record->userId);

            throw AuthenticationException::accountDisabled();
        }

        // Mark current token as used
        $this->refreshTokenRepository->markAsUsed($record->id, $now);

        // Issue new access token
        $accessClaims = TokenClaims::forAccess(
            userId: $record->userId,
            tenantId: null,
            roles: [$user->role()->value],
            now: $now,
        );

        $accessToken = $this->tokenIssuer->issue($accessClaims);

        // Generate new refresh token with parent_id pointing to used token
        $newRefreshTokenRaw = bin2hex(random_bytes(32));
        $newRefreshTokenHash = hash('sha256', $newRefreshTokenRaw);

        $newRefreshRecord = new RefreshTokenRecord(
            id: Uuid::generate(),
            userId: $record->userId,
            tokenHash: $newRefreshTokenHash,
            parentId: $record->id,
            expiresAt: $now->modify("+{$this->refreshTokenTtl} seconds"),
            usedAt: null,
            revokedAt: null,
            ipAddress: $dto->ipAddress,
            userAgent: $dto->userAgent,
            createdAt: $now,
        );

        $this->refreshTokenRepository->store($newRefreshRecord);

        $this->eventDispatcher->dispatch(new TokenRefreshed(
            userId: $record->userId,
            ipAddress: $dto->ipAddress,
        ));

        return new AuthTokensDTO(
            accessToken: $accessToken,
            refreshToken: $newRefreshTokenRaw,
            expiresIn: TokenType::Access->ttlSeconds(),
        );
    }
}
