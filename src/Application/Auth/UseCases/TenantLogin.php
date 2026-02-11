<?php

declare(strict_types=1);

namespace Application\Auth\UseCases;

use Application\Auth\Contracts\TenantConnectionManagerInterface;
use Application\Auth\Contracts\TenantRefreshTokenRepositoryInterface;
use Application\Auth\Contracts\TenantUserRepositoryInterface;
use Application\Auth\Contracts\TokenIssuerInterface;
use Application\Auth\DTOs\AuthTokensDTO;
use Application\Auth\DTOs\MfaRequiredDTO;
use Application\Auth\DTOs\RefreshTokenRecord;
use Application\Auth\DTOs\TenantLoginRequestDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Tenant\Contracts\TenantRepositoryInterface;
use DateTimeImmutable;
use Domain\Auth\Contracts\PasswordHasherInterface;
use Domain\Auth\Events\LoginFailed;
use Domain\Auth\Events\LoginSucceeded;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Auth\ValueObjects\TokenClaims;
use Domain\Auth\ValueObjects\TokenType;
use Domain\Shared\ValueObjects\Uuid;

final readonly class TenantLogin
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private TenantConnectionManagerInterface $connectionManager,
        private TenantUserRepositoryInterface $userRepository,
        private TenantRefreshTokenRepositoryInterface $refreshTokenRepository,
        private TokenIssuerInterface $tokenIssuer,
        private EventDispatcherInterface $eventDispatcher,
        private PasswordHasherInterface $passwordHasher,
        private int $refreshTokenTtl = 604800,
    ) {}

    public function execute(TenantLoginRequestDTO $dto): AuthTokensDTO|MfaRequiredDTO
    {
        $now = new DateTimeImmutable;

        // Resolve tenant by slug
        $tenant = $this->tenantRepository->findBySlug($dto->tenantSlug);

        if ($tenant === null) {
            throw AuthenticationException::invalidCredentials();
        }

        if (! $tenant->status()->isOperational()) {
            throw AuthenticationException::accountDisabled();
        }

        // Switch to tenant database
        $this->connectionManager->switchToTenant($tenant->databaseName() ?? 'tenant_'.$tenant->slug());

        $user = $this->userRepository->findByEmail($dto->email);

        if ($user === null) {
            throw AuthenticationException::invalidCredentials();
        }

        if (! $user->status()->canLogin()) {
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
                tenantId: $tenant->id(),
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
            tenantId: $tenant->id(),
            ipAddress: $dto->ipAddress,
            userAgent: $dto->userAgent,
            occurredAt: $now,
        ));

        // Check if MFA is required and configured
        if ($user->hasMfaConfigured()) {
            $mfaClaims = TokenClaims::forMfaRequired(
                userId: $user->id(),
                tenantId: $tenant->id(),
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

        return $this->issueTokens($user->id(), $tenant->id(), [$user->role()->value], $dto->ipAddress, $dto->userAgent, $now);
    }

    /**
     * @param  array<string>  $roles
     */
    private function issueTokens(
        Uuid $userId,
        Uuid $tenantId,
        array $roles,
        string $ipAddress,
        string $userAgent,
        DateTimeImmutable $now,
    ): AuthTokensDTO {
        $accessClaims = TokenClaims::forAccess(
            userId: $userId,
            tenantId: $tenantId,
            roles: $roles,
            now: $now,
        );

        $accessToken = $this->tokenIssuer->issue($accessClaims);

        $refreshTokenRaw = bin2hex(random_bytes(32));
        $refreshTokenHash = hash('sha256', $refreshTokenRaw);

        $refreshRecord = new RefreshTokenRecord(
            id: Uuid::generate(),
            userId: $userId,
            tokenHash: $refreshTokenHash,
            parentId: null,
            expiresAt: $now->modify("+{$this->refreshTokenTtl} seconds"),
            usedAt: null,
            revokedAt: null,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
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
