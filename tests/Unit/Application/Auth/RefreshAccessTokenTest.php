<?php

declare(strict_types=1);

use Application\Auth\Contracts\PlatformUserRepositoryInterface;
use Application\Auth\Contracts\RefreshTokenRepositoryInterface;
use Application\Auth\Contracts\TokenIssuerInterface;
use Application\Auth\DTOs\AuthTokensDTO;
use Application\Auth\DTOs\RefreshRequestDTO;
use Application\Auth\DTOs\RefreshTokenRecord;
use Application\Auth\UseCases\RefreshAccessToken;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Auth\Entities\PlatformUser;
use Domain\Auth\Enums\PlatformRole;
use Domain\Auth\Enums\UserStatus;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Shared\ValueObjects\Uuid;

function buildRefreshDTO(string $token = 'raw-refresh-token'): RefreshRequestDTO
{
    return new RefreshRequestDTO(
        refreshToken: $token,
        ipAddress: '127.0.0.1',
        userAgent: 'TestAgent',
    );
}

function buildRefreshTokenRecord(
    ?DateTimeImmutable $usedAt = null,
    ?DateTimeImmutable $revokedAt = null,
    ?DateTimeImmutable $expiresAt = null,
): RefreshTokenRecord {
    $now = new DateTimeImmutable;

    return new RefreshTokenRecord(
        id: Uuid::generate(),
        userId: Uuid::generate(),
        tokenHash: hash('sha256', 'raw-refresh-token'),
        parentId: null,
        expiresAt: $expiresAt ?? $now->modify('+7 days'),
        usedAt: $usedAt,
        revokedAt: $revokedAt,
        ipAddress: '127.0.0.1',
        userAgent: 'TestAgent',
        createdAt: $now,
    );
}

function buildActiveUser(?Uuid $id = null): PlatformUser
{
    return new PlatformUser(
        id: $id ?? Uuid::generate(),
        email: 'admin@test.com',
        name: 'Admin',
        passwordHash: 'hashed',
        role: PlatformRole::PlatformAdmin,
        status: UserStatus::Active,
    );
}

it('refreshes tokens successfully', function () {
    $dto = buildRefreshDTO();
    $record = buildRefreshTokenRecord();
    $user = buildActiveUser($record->userId);

    $refreshRepo = Mockery::mock(RefreshTokenRepositoryInterface::class);
    $refreshRepo->shouldReceive('findByTokenHash')
        ->with(hash('sha256', 'raw-refresh-token'))
        ->andReturn($record);
    $refreshRepo->shouldReceive('markAsUsed')->once();
    $refreshRepo->shouldReceive('store')->once();

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $userRepo->shouldReceive('findById')->with($record->userId)->andReturn($user);

    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $tokenIssuer->shouldReceive('issue')->once()->andReturn('new.access.token');

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatch')->once();

    $useCase = new RefreshAccessToken($refreshRepo, $userRepo, $tokenIssuer, $eventDispatcher);
    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(AuthTokensDTO::class)
        ->and($result->accessToken)->toBe('new.access.token')
        ->and($result->refreshToken)->toBeString();
});

it('detects token reuse and revokes chain', function () {
    $dto = buildRefreshDTO();
    $record = buildRefreshTokenRecord(usedAt: new DateTimeImmutable('-1 hour'));

    $refreshRepo = Mockery::mock(RefreshTokenRepositoryInterface::class);
    $refreshRepo->shouldReceive('findByTokenHash')->andReturn($record);
    $refreshRepo->shouldReceive('revokeChain')->with($record->id)->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatch')->once();

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);

    $useCase = new RefreshAccessToken($refreshRepo, $userRepo, $tokenIssuer, $eventDispatcher);
    $useCase->execute($dto);
})->throws(AuthenticationException::class);

it('throws on expired refresh token', function () {
    $dto = buildRefreshDTO();
    $record = buildRefreshTokenRecord(expiresAt: new DateTimeImmutable('-1 hour'));

    $refreshRepo = Mockery::mock(RefreshTokenRepositoryInterface::class);
    $refreshRepo->shouldReceive('findByTokenHash')->andReturn($record);

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new RefreshAccessToken($refreshRepo, $userRepo, $tokenIssuer, $eventDispatcher);
    $useCase->execute($dto);
})->throws(AuthenticationException::class, 'Token has expired');

it('throws when refresh token not found', function () {
    $dto = buildRefreshDTO();

    $refreshRepo = Mockery::mock(RefreshTokenRepositoryInterface::class);
    $refreshRepo->shouldReceive('findByTokenHash')->andReturnNull();

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new RefreshAccessToken($refreshRepo, $userRepo, $tokenIssuer, $eventDispatcher);
    $useCase->execute($dto);
})->throws(AuthenticationException::class);

it('throws when user is disabled during refresh', function () {
    $dto = buildRefreshDTO();
    $record = buildRefreshTokenRecord();

    $disabledUser = new PlatformUser(
        id: $record->userId,
        email: 'admin@test.com',
        name: 'Admin',
        passwordHash: 'hashed',
        role: PlatformRole::PlatformAdmin,
        status: UserStatus::Inactive,
    );

    $refreshRepo = Mockery::mock(RefreshTokenRepositoryInterface::class);
    $refreshRepo->shouldReceive('findByTokenHash')->andReturn($record);
    $refreshRepo->shouldReceive('revokeAllForUser')->with($record->userId)->once();

    $userRepo = Mockery::mock(PlatformUserRepositoryInterface::class);
    $userRepo->shouldReceive('findById')->andReturn($disabledUser);

    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new RefreshAccessToken($refreshRepo, $userRepo, $tokenIssuer, $eventDispatcher);
    $useCase->execute($dto);
})->throws(AuthenticationException::class, 'Account is disabled');
