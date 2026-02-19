<?php

declare(strict_types=1);

use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Tenant\Contracts\PendingRegistrationRepositoryInterface;
use Application\Tenant\Contracts\TenantRepositoryInterface;
use Application\Tenant\UseCases\VerifyRegistration;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Entities\Tenant;
use Domain\Tenant\Enums\CondominiumType;
use Domain\Tenant\Enums\TenantStatus;
use Domain\Tenant\Events\TenantCreated;

function buildPendingRegistrationData(array $overrides = []): array
{
    return array_merge([
        'id' => Uuid::generate()->value(),
        'slug' => 'condo-solar',
        'name' => 'Condomínio Solar',
        'type' => 'vertical',
        'admin_name' => 'João Silva',
        'admin_email' => 'joao@test.com',
        'admin_password_hash' => '$2y$10$hashed_password',
        'admin_phone' => '11999999999',
        'plan_slug' => 'basico',
        'verification_token_hash' => hash('sha256', 'valid-token'),
        'expires_at' => (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s'),
        'verified_at' => null,
    ], $overrides);
}

function verifyMockPendingRepo(?array $findResult = null): PendingRegistrationRepositoryInterface
{
    $repo = Mockery::mock(PendingRegistrationRepositoryInterface::class);
    $repo->shouldReceive('findByTokenHash')->andReturn($findResult);
    $repo->shouldReceive('markVerified')->andReturnNull();

    return $repo;
}

function verifyMockTenantRepo(?Tenant $findBySlugResult = null): TenantRepositoryInterface
{
    $repo = Mockery::mock(TenantRepositoryInterface::class);
    $repo->shouldReceive('findBySlug')->andReturn($findBySlugResult);
    $repo->shouldReceive('save')->andReturnNull();
    $repo->shouldReceive('saveConfig')->andReturnNull();

    return $repo;
}

function verifyMockDispatcher(): EventDispatcherInterface
{
    $dispatcher = Mockery::mock(EventDispatcherInterface::class);
    $dispatcher->shouldReceive('dispatch')->andReturnNull();

    return $dispatcher;
}

function buildVerifyUseCase(
    ?PendingRegistrationRepositoryInterface $pendingRepo = null,
    ?TenantRepositoryInterface $tenantRepo = null,
    ?EventDispatcherInterface $dispatcher = null,
): VerifyRegistration {
    return new VerifyRegistration(
        $pendingRepo ?? verifyMockPendingRepo(buildPendingRegistrationData()),
        $tenantRepo ?? verifyMockTenantRepo(),
        $dispatcher ?? verifyMockDispatcher(),
    );
}

test('verifies registration and creates tenant', function () {
    $useCase = buildVerifyUseCase();
    $tenant = $useCase->execute('valid-token');

    expect($tenant)->toBeInstanceOf(Tenant::class)
        ->and($tenant->slug())->toBe('condo-solar')
        ->and($tenant->name())->toBe('Condomínio Solar')
        ->and($tenant->type())->toBe(CondominiumType::Vertical)
        ->and($tenant->status())->toBe(TenantStatus::Provisioning);
});

test('dispatches TenantCreated event on verification', function () {
    $dispatcher = Mockery::mock(EventDispatcherInterface::class);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->withArgs(fn ($event) => $event instanceof TenantCreated
            && $event->eventName() === 'tenant.created'
            && $event->payload()['slug'] === 'condo-solar',
        );

    $useCase = buildVerifyUseCase(dispatcher: $dispatcher);
    $useCase->execute('valid-token');
});

test('saves tenant with provisioning status', function () {
    $tenantRepo = Mockery::mock(TenantRepositoryInterface::class);
    $tenantRepo->shouldReceive('findBySlug')->andReturnNull();
    $tenantRepo->shouldReceive('save')
        ->once()
        ->withArgs(fn (Tenant $t) => $t->slug() === 'condo-solar'
            && $t->status() === TenantStatus::Provisioning,
        );
    $tenantRepo->shouldReceive('saveConfig')->andReturnNull();

    $useCase = buildVerifyUseCase(tenantRepo: $tenantRepo);
    $useCase->execute('valid-token');
});

test('saves admin config from pending registration', function () {
    $tenantRepo = Mockery::mock(TenantRepositoryInterface::class);
    $tenantRepo->shouldReceive('findBySlug')->andReturnNull();
    $tenantRepo->shouldReceive('save')->andReturnNull();
    $tenantRepo->shouldReceive('saveConfig')
        ->once()
        ->withArgs(function (Uuid $id, ?array $config) {
            return $config !== null
                && $config['admin_name'] === 'João Silva'
                && $config['admin_email'] === 'joao@test.com'
                && $config['admin_password_hash'] === '$2y$10$hashed_password'
                && $config['admin_phone'] === '11999999999'
                && $config['plan_slug'] === 'basico';
        });

    $useCase = buildVerifyUseCase(tenantRepo: $tenantRepo);
    $useCase->execute('valid-token');
});

test('marks pending registration as verified', function () {
    $pendingRepo = Mockery::mock(PendingRegistrationRepositoryInterface::class);
    $pendingRepo->shouldReceive('findByTokenHash')->andReturn(buildPendingRegistrationData());
    $pendingRepo->shouldReceive('markVerified')->once();

    $useCase = buildVerifyUseCase(pendingRepo: $pendingRepo);
    $useCase->execute('valid-token');
});

test('throws on invalid token', function () {
    $pendingRepo = verifyMockPendingRepo(null);

    $useCase = buildVerifyUseCase(pendingRepo: $pendingRepo);
    $useCase->execute('invalid-token');
})->throws(DomainException::class, 'Invalid or already used verification token');

test('invalid token throws with correct error code', function () {
    $pendingRepo = verifyMockPendingRepo(null);
    $useCase = buildVerifyUseCase(pendingRepo: $pendingRepo);

    try {
        $useCase->execute('invalid-token');
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('VERIFICATION_TOKEN_INVALID');
    }
});

test('throws on expired token', function () {
    $expiredData = buildPendingRegistrationData([
        'expires_at' => (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s'),
    ]);

    $pendingRepo = verifyMockPendingRepo($expiredData);
    $useCase = buildVerifyUseCase(pendingRepo: $pendingRepo);
    $useCase->execute('valid-token');
})->throws(DomainException::class, 'Verification token has expired');

test('expired token throws with correct error code', function () {
    $expiredData = buildPendingRegistrationData([
        'expires_at' => (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s'),
    ]);

    $pendingRepo = verifyMockPendingRepo($expiredData);
    $useCase = buildVerifyUseCase(pendingRepo: $pendingRepo);

    try {
        $useCase->execute('valid-token');
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('VERIFICATION_TOKEN_EXPIRED');
    }
});

test('throws when slug already taken by another tenant (race condition)', function () {
    $existingTenant = Tenant::create(
        Uuid::generate(),
        'condo-solar',
        'Outro Condomínio',
        CondominiumType::Vertical,
    );

    $tenantRepo = Mockery::mock(TenantRepositoryInterface::class);
    $tenantRepo->shouldReceive('findBySlug')->andReturn($existingTenant);

    $useCase = buildVerifyUseCase(tenantRepo: $tenantRepo);
    $useCase->execute('valid-token');
})->throws(DomainException::class, "Tenant with slug 'condo-solar' already exists");
