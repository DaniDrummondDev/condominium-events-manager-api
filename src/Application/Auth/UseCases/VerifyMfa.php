<?php

declare(strict_types=1);

namespace Application\Auth\UseCases;

use Application\Auth\Contracts\PlatformUserRepositoryInterface;
use Application\Auth\Contracts\RefreshTokenRepositoryInterface;
use Application\Auth\Contracts\TokenIssuerInterface;
use Application\Auth\Contracts\TokenValidatorInterface;
use Application\Auth\Contracts\TotpServiceInterface;
use Application\Auth\DTOs\AuthTokensDTO;
use Application\Auth\DTOs\MfaVerifyRequestDTO;
use Application\Auth\DTOs\RefreshTokenRecord;
use Application\Shared\Contracts\EventDispatcherInterface;
use DateTimeImmutable;
use Domain\Auth\Events\MfaVerified;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Auth\ValueObjects\TokenClaims;
use Domain\Auth\ValueObjects\TokenType;
use Domain\Shared\ValueObjects\Uuid;

final readonly class VerifyMfa
{
    public function __construct(
        private TokenValidatorInterface $tokenValidator,
        private PlatformUserRepositoryInterface $userRepository,
        private TotpServiceInterface $totpService,
        private TokenIssuerInterface $tokenIssuer,
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private EventDispatcherInterface $eventDispatcher,
        private int $refreshTokenTtl = 604800,
    ) {}

    public function execute(MfaVerifyRequestDTO $dto): AuthTokensDTO
    {
        $now = new DateTimeImmutable;

        // Validate MFA token
        $claims = $this->tokenValidator->validate($dto->mfaToken);

        if ($claims->tokenType !== TokenType::MfaRequired) {
            throw AuthenticationException::invalidToken('Expected MFA token');
        }

        $user = $this->userRepository->findById($claims->sub);

        if ($user === null || ! $user->status()->isActive()) {
            throw AuthenticationException::accountDisabled();
        }

        if ($user->isLocked($now)) {
            throw AuthenticationException::accountLocked(
                $user->lockoutRemainingMinutes($now),
            );
        }

        if ($user->mfaSecret() === null) {
            throw AuthenticationException::invalidToken('MFA not configured');
        }

        if (! $this->totpService->verify($user->mfaSecret(), $dto->code)) {
            $user->incrementFailedAttempts($now);
            $this->userRepository->save($user);

            throw AuthenticationException::invalidCredentials();
        }

        $user->recordLogin($now);
        $this->userRepository->save($user);

        $this->eventDispatcher->dispatch(new MfaVerified(
            userId: $user->id(),
            ipAddress: $dto->ipAddress,
        ));

        // Issue access + refresh tokens
        $accessClaims = TokenClaims::forAccess(
            userId: $user->id(),
            tenantId: $claims->tenantId,
            roles: $claims->roles,
            now: $now,
        );

        $accessToken = $this->tokenIssuer->issue($accessClaims);

        $refreshTokenRaw = bin2hex(random_bytes(32));
        $refreshTokenHash = hash('sha256', $refreshTokenRaw);

        $refreshRecord = new RefreshTokenRecord(
            id: Uuid::generate(),
            userId: $user->id(),
            tokenHash: $refreshTokenHash,
            parentId: null,
            expiresAt: $now->modify("+{$this->refreshTokenTtl} seconds"),
            usedAt: null,
            revokedAt: null,
            ipAddress: $dto->ipAddress,
            userAgent: $dto->userAgent,
            createdAt: $now,
        );

        $this->refreshTokenRepository->store($refreshRecord);

        return new AuthTokensDTO(
            accessToken: $accessToken,
            refreshToken: $refreshTokenRaw,
            expiresIn: TokenType::Access->ttlSeconds(),
        );
    }
}
