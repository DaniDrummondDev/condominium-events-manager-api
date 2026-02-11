<?php

declare(strict_types=1);

use Application\Auth\Contracts\PlatformUserRepositoryInterface;
use Application\Auth\Contracts\RefreshTokenRepositoryInterface;
use Application\Auth\Contracts\TokenIssuerInterface;
use Application\Auth\Contracts\TokenValidatorInterface;
use Application\Auth\Contracts\TotpServiceInterface;
use Application\Auth\DTOs\AuthTokensDTO;
use Application\Auth\DTOs\MfaVerifyRequestDTO;
use Application\Auth\UseCases\VerifyMfa;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Auth\Entities\PlatformUser;
use Domain\Auth\Enums\PlatformRole;
use Domain\Auth\Enums\UserStatus;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Auth\ValueObjects\JtiToken;
use Domain\Auth\ValueObjects\TokenClaims;
use Domain\Auth\ValueObjects\TokenType;
use Domain\Shared\ValueObjects\Uuid;

function buildMfaVerifyDTO(): MfaVerifyRequestDTO
{
    return new MfaVerifyRequestDTO(
        mfaToken: 'mfa.jwt.token',
        code: '123456',
        ipAddress: '127.0.0.1',
        userAgent: 'TestAgent',
    );
}

function buildMfaClaims(?Uuid $userId = null): TokenClaims
{
    $now = new DateTimeImmutable;

    return new TokenClaims(
        sub: $userId ?? Uuid::generate(),
        tenantId: null,
        roles: ['platform_admin'],
        tokenType: TokenType::MfaRequired,
        jti: JtiToken::generate(TokenType::MfaRequired),
        issuedAt: $now,
        expiresAt: $now->modify('+300 seconds'),
    );
}

function buildMfaUser(?Uuid $id = null): PlatformUser
{
    return new PlatformUser(
        id: $id ?? Uuid::generate(),
        email: 'admin@test.com',
        name: 'Admin',
        passwordHash: 'hashed',
        role: PlatformRole::PlatformAdmin,
        status: UserStatus::Active,
        mfaEnabled: true,
        mfaSecret: 'TOTP_SECRET',
    );
}

it('verifies MFA successfully and returns tokens', function () {
    $dto = buildMfaVerifyDTO();
    $userId = Uuid::generate();
    $claims = buildMfaClaims($userId);
    $user = buildMfaUser($userId);

    $tokenValidator = Mockery::mock(TokenValidatorInterface::class);
    $tokenValidator->shouldReceive('validate')->with('mfa.jwt.token')->andReturn($claims);

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $userRepo->shouldReceive('findById')->with($userId)->andReturn($user);
    $userRepo->shouldReceive('save')->once();

    $totpService = Mockery::mock(TotpServiceInterface::class);
    $totpService->shouldReceive('verify')->with('TOTP_SECRET', '123456')->andReturnTrue();

    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $tokenIssuer->shouldReceive('issue')->once()->andReturn('access.jwt.token');

    $refreshRepo = Mockery::mock(RefreshTokenRepositoryInterface::class);
    $refreshRepo->shouldReceive('store')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatch')->once();

    $useCase = new VerifyMfa(
        $tokenValidator, $userRepo, $totpService,
        $tokenIssuer, $refreshRepo, $eventDispatcher,
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(AuthTokensDTO::class)
        ->and($result->accessToken)->toBe('access.jwt.token')
        ->and($result->expiresIn)->toBe(900);
});

it('throws on invalid TOTP code and increments failed attempts', function () {
    $dto = buildMfaVerifyDTO();
    $userId = Uuid::generate();
    $claims = buildMfaClaims($userId);
    $user = buildMfaUser($userId);

    $tokenValidator = Mockery::mock(TokenValidatorInterface::class);
    $tokenValidator->shouldReceive('validate')->andReturn($claims);

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $userRepo->shouldReceive('findById')->andReturn($user);
    $userRepo->shouldReceive('save')->once();

    $totpService = Mockery::mock(TotpServiceInterface::class);
    $totpService->shouldReceive('verify')->andReturnFalse();

    $useCase = new VerifyMfa(
        $tokenValidator, $userRepo, $totpService,
        Mockery::mock(TokenIssuerInterface::class),
        Mockery::mock(RefreshTokenRepositoryInterface::class),
        Mockery::mock(EventDispatcherInterface::class),
    );

    $useCase->execute($dto);
})->throws(AuthenticationException::class, 'Invalid email or password');

it('throws when token type is not MfaRequired', function () {
    $dto = buildMfaVerifyDTO();
    $now = new DateTimeImmutable;

    $accessClaims = new TokenClaims(
        sub: Uuid::generate(),
        tenantId: null,
        roles: ['platform_admin'],
        tokenType: TokenType::Access,
        jti: JtiToken::generate(TokenType::Access),
        issuedAt: $now,
        expiresAt: $now->modify('+900 seconds'),
    );

    $tokenValidator = Mockery::mock(TokenValidatorInterface::class);
    $tokenValidator->shouldReceive('validate')->andReturn($accessClaims);

    $useCase = new VerifyMfa(
        $tokenValidator,
        Mockery::mock(PlatformUserRepositoryInterface::class),
        Mockery::mock(TotpServiceInterface::class),
        Mockery::mock(TokenIssuerInterface::class),
        Mockery::mock(RefreshTokenRepositoryInterface::class),
        Mockery::mock(EventDispatcherInterface::class),
    );

    $useCase->execute($dto);
})->throws(AuthenticationException::class, 'Expected MFA token');

it('throws when user is disabled', function () {
    $dto = buildMfaVerifyDTO();
    $userId = Uuid::generate();
    $claims = buildMfaClaims($userId);

    $disabledUser = new PlatformUser(
        id: $userId,
        email: 'admin@test.com',
        name: 'Admin',
        passwordHash: 'hashed',
        role: PlatformRole::PlatformAdmin,
        status: UserStatus::Inactive,
        mfaEnabled: true,
        mfaSecret: 'SECRET',
    );

    $tokenValidator = Mockery::mock(TokenValidatorInterface::class);
    $tokenValidator->shouldReceive('validate')->andReturn($claims);

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $userRepo->shouldReceive('findById')->andReturn($disabledUser);

    $useCase = new VerifyMfa(
        $tokenValidator, $userRepo,
        Mockery::mock(TotpServiceInterface::class),
        Mockery::mock(TokenIssuerInterface::class),
        Mockery::mock(RefreshTokenRepositoryInterface::class),
        Mockery::mock(EventDispatcherInterface::class),
    );

    $useCase->execute($dto);
})->throws(AuthenticationException::class, 'Account is disabled');

it('throws when user account is locked', function () {
    $dto = buildMfaVerifyDTO();
    $userId = Uuid::generate();
    $claims = buildMfaClaims($userId);

    $lockedUser = new PlatformUser(
        id: $userId,
        email: 'admin@test.com',
        name: 'Admin',
        passwordHash: 'hashed',
        role: PlatformRole::PlatformAdmin,
        status: UserStatus::Active,
        mfaEnabled: true,
        mfaSecret: 'SECRET',
        failedLoginAttempts: 10,
        lockedUntil: new DateTimeImmutable('+15 minutes'),
    );

    $tokenValidator = Mockery::mock(TokenValidatorInterface::class);
    $tokenValidator->shouldReceive('validate')->andReturn($claims);

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $userRepo->shouldReceive('findById')->andReturn($lockedUser);

    $useCase = new VerifyMfa(
        $tokenValidator, $userRepo,
        Mockery::mock(TotpServiceInterface::class),
        Mockery::mock(TokenIssuerInterface::class),
        Mockery::mock(RefreshTokenRepositoryInterface::class),
        Mockery::mock(EventDispatcherInterface::class),
    );

    $useCase->execute($dto);
})->throws(AuthenticationException::class, 'Account is locked');
