<?php

declare(strict_types=1);

namespace Application\Auth\UseCases;

use Application\Auth\Contracts\PlatformUserRepositoryInterface;
use Application\Auth\Contracts\RefreshTokenRepositoryInterface;
use Application\Auth\Contracts\TokenIssuerInterface;
use Application\Auth\DTOs\AuthTokensDTO;
use Application\Auth\DTOs\LoginRequestDTO;
use Application\Auth\DTOs\MfaRequiredDTO;
use Application\Auth\DTOs\RefreshTokenRecord;
use Application\Shared\Contracts\EventDispatcherInterface;
use DateTimeImmutable;
use Domain\Auth\Contracts\PasswordHasherInterface;
use Domain\Auth\Events\LoginFailed;
use Domain\Auth\Events\LoginSucceeded;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Auth\ValueObjects\TokenClaims;
use Domain\Auth\ValueObjects\TokenType;
use Domain\Shared\ValueObjects\Uuid;

final readonly class PlatformLogin
{
    public function __construct(
        private PlatformUserRepositoryInterface $userRepository,
        private TokenIssuerInterface $tokenIssuer,
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private EventDispatcherInterface $eventDispatcher,
        private PasswordHasherInterface $passwordHasher,
        private int $refreshTokenTtl = 604800,
    ) {}

    public function execute(LoginRequestDTO $dto): AuthTokensDTO|MfaRequiredDTO
    {
        $now = new DateTimeImmutable;
        $user = $this->userRepository->findByEmail($dto->email);

        if ($user === null) {
            throw AuthenticationException::invalidCredentials();
        }

        if (! $user->status()->isActive()) {
            throw AuthenticationException::accountDisabled();
        }

        if ($user->isLocked($now)) {
            throw AuthenticationException::accountLocked(
                $user->lockoutRemainingMinutes($now),
            );
        }

        if (! $user->verifyPassword($dto->password, $this->passwordHasher)) {
            $user->incrementFailedAttempts($now);
            $this->userRepository->save($user);

            $this->eventDispatcher->dispatch(new LoginFailed(
                email: $dto->email,
                reason: 'invalid_credentials',
                tenantId: null,
                ipAddress: $dto->ipAddress,
                failedAttempts: $user->failedLoginAttempts(),
                occurredAt: $now,
            ));

            throw AuthenticationException::invalidCredentials();
        }

        $user->recordLogin($now);
        $this->userRepository->save($user);

        $this->eventDispatcher->dispatch(new LoginSucceeded(
            userId: $user->id(),
            role: $user->role()->value,
            tenantId: null,
            ipAddress: $dto->ipAddress,
            userAgent: $dto->userAgent,
            occurredAt: $now,
        ));

        // Check if MFA is required and configured
        if ($user->hasMfaConfigured()) {
            $mfaClaims = TokenClaims::forMfaRequired(
                userId: $user->id(),
                tenantId: null,
                roles: [$user->role()->value],
                now: $now,
            );

            $mfaToken = $this->tokenIssuer->issue($mfaClaims);

            return new MfaRequiredDTO(
                mfaRequired: true,
                mfaToken: $mfaToken,
                mfaTokenExpiresIn: TokenType::MfaRequired->ttlSeconds(),
            );
        }

        return $this->issueTokens($user->id(), [$user->role()->value], $dto, $now);
    }

    /**
     * @param  array<string>  $roles
     */
    private function issueTokens(
        Uuid $userId,
        array $roles,
        LoginRequestDTO $dto,
        DateTimeImmutable $now,
    ): AuthTokensDTO {
        $accessClaims = TokenClaims::forAccess(
            userId: $userId,
            tenantId: null,
            roles: $roles,
            now: $now,
        );

        $accessToken = $this->tokenIssuer->issue($accessClaims);

        // Generate opaque refresh token
        $refreshTokenRaw = bin2hex(random_bytes(32));
        $refreshTokenHash = hash('sha256', $refreshTokenRaw);
        $refreshTtl = $this->refreshTokenTtl;

        $refreshRecord = new RefreshTokenRecord(
            id: Uuid::generate(),
            userId: $userId,
            tokenHash: $refreshTokenHash,
            parentId: null,
            expiresAt: $now->modify("+{$refreshTtl} seconds"),
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
