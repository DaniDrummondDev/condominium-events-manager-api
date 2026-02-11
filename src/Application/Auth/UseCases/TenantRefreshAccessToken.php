<?php

declare(strict_types=1);

namespace Application\Auth\UseCases;

use Application\Auth\Contracts\TenantRefreshTokenRepositoryInterface;
use Application\Auth\Contracts\TenantUserRepositoryInterface;
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

final readonly class TenantRefreshAccessToken
{
    public function __construct(
        private TenantRefreshTokenRepositoryInterface $refreshTokenRepository,
        private TenantUserRepositoryInterface $userRepository,
        private TokenIssuerInterface $tokenIssuer,
        private EventDispatcherInterface $eventDispatcher,
        private int $refreshTokenTtl = 604800,
    ) {}

    public function execute(RefreshRequestDTO $dto, Uuid $tenantId): AuthTokensDTO
    {
        $now = new DateTimeImmutable;
        $tokenHash = hash('sha256', $dto->refreshToken);

        $record = $this->refreshTokenRepository->findByTokenHash($tokenHash);

        if ($record === null) {
            throw AuthenticationException::invalidToken();
        }

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

        $user = $this->userRepository->findById($record->userId);

        if ($user === null || ! $user->status()->canLogin()) {
            $this->refreshTokenRepository->revokeAllForUser($record->userId);

            throw AuthenticationException::accountDisabled();
        }

        $this->refreshTokenRepository->markAsUsed($record->id, $now);

        $accessClaims = TokenClaims::forAccess(
            userId: $record->userId,
            tenantId: $tenantId,
            roles: [$user->role()->value],
            now: $now,
        );

        $accessToken = $this->tokenIssuer->issue($accessClaims);

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
