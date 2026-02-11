<?php

declare(strict_types=1);

use Application\Auth\Contracts\PlatformUserRepositoryInterface;
use Application\Auth\Contracts\RefreshTokenRepositoryInterface;
use Application\Auth\Contracts\TokenIssuerInterface;
use Application\Auth\DTOs\AuthTokensDTO;
use Application\Auth\DTOs\LoginRequestDTO;
use Application\Auth\DTOs\MfaRequiredDTO;
use Application\Auth\UseCases\PlatformLogin;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Auth\Contracts\PasswordHasherInterface;
use Domain\Auth\Entities\PlatformUser;
use Domain\Auth\Enums\PlatformRole;
use Domain\Auth\Enums\UserStatus;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Shared\ValueObjects\Uuid;

function buildPlatformUser(
    ?PlatformRole $role = null,
    ?UserStatus $status = null,
    int $failedAttempts = 0,
    ?DateTimeImmutable $lockedUntil = null,
    bool $mfaEnabled = false,
    ?string $mfaSecret = null,
): PlatformUser {
    return new PlatformUser(
        id: Uuid::generate(),
        email: 'admin@test.com',
        name: 'Admin',
        passwordHash: 'hashed_password',
        role: $role ?? PlatformRole::PlatformAdmin,
        status: $status ?? UserStatus::Active,
        mfaEnabled: $mfaEnabled,
        mfaSecret: $mfaSecret,
        failedLoginAttempts: $failedAttempts,
        lockedUntil: $lockedUntil,
    );
}

function buildLoginDTO(): LoginRequestDTO
{
    return new LoginRequestDTO(
        email: 'admin@test.com',
        password: 'Password1',
        ipAddress: '127.0.0.1',
        userAgent: 'TestAgent',
    );
}

function buildPlatformLoginUseCase(
    ?PlatformUserRepositoryInterface $userRepo = null,
    ?TokenIssuerInterface $tokenIssuer = null,
    ?RefreshTokenRepositoryInterface $refreshRepo = null,
    ?EventDispatcherInterface $eventDispatcher = null,
    ?PasswordHasherInterface $passwordHasher = null,
): PlatformLogin {
    return new PlatformLogin(
        userRepository: $userRepo ?? Mockery::mock(PlatformUserRepositoryInterface::class),
        tokenIssuer: $tokenIssuer ?? Mockery::mock(TokenIssuerInterface::class),
        refreshTokenRepository: $refreshRepo ?? Mockery::mock(RefreshTokenRepositoryInterface::class),
        eventDispatcher: $eventDispatcher ?? Mockery::mock(EventDispatcherInterface::class),
        passwordHasher: $passwordHasher ?? Mockery::mock(PasswordHasherInterface::class),
    );
}

it('returns tokens on successful login', function () {
    $user = buildPlatformUser();
    $dto = buildLoginDTO();

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $userRepo->shouldReceive('findByEmail')->with($dto->email)->andReturn($user);
    $userRepo->shouldReceive('save')->once();

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')->with($dto->password, 'hashed_password')->andReturnTrue();

    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $tokenIssuer->shouldReceive('issue')->once()->andReturn('jwt.access.token');

    $refreshRepo = Mockery::mock(RefreshTokenRepositoryInterface::class);
    $refreshRepo->shouldReceive('store')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatch')->once();

    $useCase = new PlatformLogin($userRepo, $tokenIssuer, $refreshRepo, $eventDispatcher, $hasher);
    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(AuthTokensDTO::class)
        ->and($result->accessToken)->toBe('jwt.access.token')
        ->and($result->refreshToken)->toBeString()
        ->and($result->expiresIn)->toBe(900);
});

it('returns MFA required when user has MFA configured', function () {
    $user = buildPlatformUser(
        role: PlatformRole::PlatformOwner,
        mfaEnabled: true,
        mfaSecret: 'SECRET123',
    );
    $dto = buildLoginDTO();

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $userRepo->shouldReceive('findByEmail')->andReturn($user);
    $userRepo->shouldReceive('save')->once();

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')->andReturnTrue();

    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $tokenIssuer->shouldReceive('issue')->once()->andReturn('mfa.token');

    $refreshRepo = Mockery::mock(RefreshTokenRepositoryInterface::class);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatch')->once();

    $useCase = new PlatformLogin($userRepo, $tokenIssuer, $refreshRepo, $eventDispatcher, $hasher);
    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(MfaRequiredDTO::class)
        ->and($result->mfaRequired)->toBeTrue()
        ->and($result->mfaToken)->toBe('mfa.token')
        ->and($result->mfaTokenExpiresIn)->toBe(300);
});

it('throws on invalid credentials (user not found)', function () {
    $dto = buildLoginDTO();

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $userRepo->shouldReceive('findByEmail')->andReturnNull();

    $useCase = buildPlatformLoginUseCase(userRepo: $userRepo);
    $useCase->execute($dto);
})->throws(AuthenticationException::class, 'Invalid email or password');

it('throws on wrong password and increments failed attempts', function () {
    $user = buildPlatformUser();
    $dto = buildLoginDTO();

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $userRepo->shouldReceive('findByEmail')->andReturn($user);
    $userRepo->shouldReceive('save')->once();

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')->andReturnFalse();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatch')->once();

    $useCase = buildPlatformLoginUseCase(
        userRepo: $userRepo,
        eventDispatcher: $eventDispatcher,
        passwordHasher: $hasher,
    );
    $useCase->execute($dto);
})->throws(AuthenticationException::class, 'Invalid email or password');

it('throws on disabled account', function () {
    $user = buildPlatformUser(status: UserStatus::Inactive);
    $dto = buildLoginDTO();

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $userRepo->shouldReceive('findByEmail')->andReturn($user);

    $useCase = buildPlatformLoginUseCase(userRepo: $userRepo);
    $useCase->execute($dto);
})->throws(AuthenticationException::class, 'Account is disabled');

it('throws on locked account', function () {
    $user = buildPlatformUser(lockedUntil: new DateTimeImmutable('+15 minutes'));
    $dto = buildLoginDTO();

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $userRepo->shouldReceive('findByEmail')->andReturn($user);

    $useCase = buildPlatformLoginUseCase(userRepo: $userRepo);
    $useCase->execute($dto);
})->throws(AuthenticationException::class, 'Account is locked');
