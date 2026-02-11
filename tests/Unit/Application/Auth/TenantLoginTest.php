<?php

declare(strict_types=1);

use Application\Auth\Contracts\TenantConnectionManagerInterface;
use Application\Auth\Contracts\TenantRefreshTokenRepositoryInterface;
use Application\Auth\Contracts\TenantUserRepositoryInterface;
use Application\Auth\Contracts\TokenIssuerInterface;
use Application\Auth\DTOs\AuthTokensDTO;
use Application\Auth\DTOs\TenantLoginRequestDTO;
use Application\Auth\UseCases\TenantLogin;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Tenant\Contracts\TenantRepositoryInterface;
use Domain\Auth\Contracts\PasswordHasherInterface;
use Domain\Auth\Entities\TenantUser;
use Domain\Auth\Enums\TenantRole;
use Domain\Auth\Enums\TenantUserStatus;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Entities\Tenant;
use Domain\Tenant\Enums\CondominiumType;
use Domain\Tenant\Enums\TenantStatus;

function buildTenantLoginDTO(): TenantLoginRequestDTO
{
    return new TenantLoginRequestDTO(
        email: 'morador@test.com',
        password: 'Password1',
        tenantSlug: 'cond-test',
        ipAddress: '127.0.0.1',
        userAgent: 'TestAgent',
    );
}

function buildTenant(?TenantStatus $status = null): Tenant
{
    return new Tenant(
        id: Uuid::generate(),
        slug: 'cond-test',
        name: 'Condomínio Test',
        type: CondominiumType::Vertical,
        status: $status ?? TenantStatus::Active,
        databaseName: 'tenant_cond_test',
    );
}

function buildTenantUserForLogin(
    ?TenantRole $role = null,
    ?TenantUserStatus $status = null,
    bool $mfaEnabled = false,
    ?string $mfaSecret = null,
): TenantUser {
    return new TenantUser(
        id: Uuid::generate(),
        email: 'morador@test.com',
        name: 'Morador',
        passwordHash: 'hashed',
        role: $role ?? TenantRole::Condomino,
        status: $status ?? TenantUserStatus::Active,
        phone: '11999999999',
        mfaEnabled: $mfaEnabled,
        mfaSecret: $mfaSecret,
    );
}

it('logs in tenant user successfully', function () {
    $dto = buildTenantLoginDTO();
    $tenant = buildTenant();
    $user = buildTenantUserForLogin();

    $tenantRepo = Mockery::mock(TenantRepositoryInterface::class);
    $tenantRepo->shouldReceive('findBySlug')->with('cond-test')->andReturn($tenant);

    $connManager = Mockery::mock(TenantConnectionManagerInterface::class);
    $connManager->shouldReceive('switchToTenant')->with('tenant_cond_test')->once();

    $userRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $userRepo->shouldReceive('findByEmail')->with('morador@test.com')->andReturn($user);
    $userRepo->shouldReceive('save')->once();

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')->andReturnTrue();

    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $tokenIssuer->shouldReceive('issue')->once()->andReturn('jwt.token');

    $refreshRepo = Mockery::mock(TenantRefreshTokenRepositoryInterface::class);
    $refreshRepo->shouldReceive('store')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatch')->once();

    $useCase = new TenantLogin(
        $tenantRepo, $connManager, $userRepo, $refreshRepo,
        $tokenIssuer, $eventDispatcher, $hasher,
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(AuthTokensDTO::class)
        ->and($result->accessToken)->toBe('jwt.token');
});

it('throws when tenant slug not found', function () {
    $dto = buildTenantLoginDTO();

    $tenantRepo = Mockery::mock(TenantRepositoryInterface::class);
    $tenantRepo->shouldReceive('findBySlug')->andReturnNull();

    $useCase = new TenantLogin(
        $tenantRepo,
        Mockery::mock(TenantConnectionManagerInterface::class),
        Mockery::mock(TenantUserRepositoryInterface::class),
        Mockery::mock(TenantRefreshTokenRepositoryInterface::class),
        Mockery::mock(TokenIssuerInterface::class),
        Mockery::mock(EventDispatcherInterface::class),
        Mockery::mock(PasswordHasherInterface::class),
    );

    $useCase->execute($dto);
})->throws(AuthenticationException::class, 'Invalid email or password');

it('throws when tenant is suspended', function () {
    $dto = buildTenantLoginDTO();
    $tenant = buildTenant(TenantStatus::Suspended);

    $tenantRepo = Mockery::mock(TenantRepositoryInterface::class);
    $tenantRepo->shouldReceive('findBySlug')->andReturn($tenant);

    $useCase = new TenantLogin(
        $tenantRepo,
        Mockery::mock(TenantConnectionManagerInterface::class),
        Mockery::mock(TenantUserRepositoryInterface::class),
        Mockery::mock(TenantRefreshTokenRepositoryInterface::class),
        Mockery::mock(TokenIssuerInterface::class),
        Mockery::mock(EventDispatcherInterface::class),
        Mockery::mock(PasswordHasherInterface::class),
    );

    $useCase->execute($dto);
})->throws(AuthenticationException::class, 'Account is disabled');

it('throws when tenant user cannot login (invited status)', function () {
    $dto = buildTenantLoginDTO();
    $tenant = buildTenant();
    $user = buildTenantUserForLogin(status: TenantUserStatus::Invited);

    $tenantRepo = Mockery::mock(TenantRepositoryInterface::class);
    $tenantRepo->shouldReceive('findBySlug')->andReturn($tenant);

    $connManager = Mockery::mock(TenantConnectionManagerInterface::class);
    $connManager->shouldReceive('switchToTenant');

    $userRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $userRepo->shouldReceive('findByEmail')->andReturn($user);

    $useCase = new TenantLogin(
        $tenantRepo, $connManager, $userRepo,
        Mockery::mock(TenantRefreshTokenRepositoryInterface::class),
        Mockery::mock(TokenIssuerInterface::class),
        Mockery::mock(EventDispatcherInterface::class),
        Mockery::mock(PasswordHasherInterface::class),
    );

    $useCase->execute($dto);
})->throws(AuthenticationException::class, 'Account is disabled');

it('switches to tenant database connection', function () {
    $dto = buildTenantLoginDTO();
    $tenant = buildTenant();
    $user = buildTenantUserForLogin();

    $tenantRepo = Mockery::mock(TenantRepositoryInterface::class);
    $tenantRepo->shouldReceive('findBySlug')->andReturn($tenant);

    $connManager = Mockery::mock(TenantConnectionManagerInterface::class);
    $connManager->shouldReceive('switchToTenant')
        ->with('tenant_cond_test')
        ->once();

    $userRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $userRepo->shouldReceive('findByEmail')->andReturn($user);
    $userRepo->shouldReceive('save');

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')->andReturnTrue();

    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $tokenIssuer->shouldReceive('issue')->andReturn('jwt');

    $refreshRepo = Mockery::mock(TenantRefreshTokenRepositoryInterface::class);
    $refreshRepo->shouldReceive('store');

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatch');

    $useCase = new TenantLogin(
        $tenantRepo, $connManager, $userRepo, $refreshRepo,
        $tokenIssuer, $eventDispatcher, $hasher,
    );

    $useCase->execute($dto);
});
