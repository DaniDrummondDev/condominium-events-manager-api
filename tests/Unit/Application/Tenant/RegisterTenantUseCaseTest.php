<?php

declare(strict_types=1);

use Application\Billing\Contracts\PlanRepositoryInterface;
use Application\Shared\Contracts\NotificationServiceInterface;
use Application\Tenant\Contracts\PendingRegistrationRepositoryInterface;
use Application\Tenant\Contracts\TenantRepositoryInterface;
use Application\Tenant\DTOs\PendingRegistrationDTO;
use Application\Tenant\DTOs\RegisterTenantDTO;
use Application\Tenant\UseCases\RegisterTenant;
use Domain\Auth\Contracts\PasswordHasherInterface;
use Domain\Billing\Entities\Plan;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Entities\Tenant;
use Domain\Tenant\Enums\CondominiumType;

function registerTenantMockTenantRepo(?Tenant $findBySlugResult = null): TenantRepositoryInterface
{
    $repo = Mockery::mock(TenantRepositoryInterface::class);
    $repo->shouldReceive('findBySlug')->andReturn($findBySlugResult);

    return $repo;
}

function registerTenantMockPendingRepo(?array $findActiveBySlugResult = null): PendingRegistrationRepositoryInterface
{
    $repo = Mockery::mock(PendingRegistrationRepositoryInterface::class);
    $repo->shouldReceive('findActiveBySlug')->andReturn($findActiveBySlugResult);
    $repo->shouldReceive('save')->andReturnNull();

    return $repo;
}

function registerTenantMockPlanRepo(?Plan $findBySlugResult = null): PlanRepositoryInterface
{
    $repo = Mockery::mock(PlanRepositoryInterface::class);
    $repo->shouldReceive('findBySlug')->andReturn($findBySlugResult);

    return $repo;
}

function registerTenantMockHasher(): PasswordHasherInterface
{
    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')->andReturn('$2y$10$hashed_password');

    return $hasher;
}

function registerTenantMockNotification(): NotificationServiceInterface
{
    $service = Mockery::mock(NotificationServiceInterface::class);
    $service->shouldReceive('send')->andReturnNull();

    return $service;
}

function buildActivePlan(string $slug = 'basico'): Plan
{
    return Plan::create(Uuid::generate(), 'Básico', $slug);
}

function buildInactivePlan(string $slug = 'inativo'): Plan
{
    $plan = Plan::create(Uuid::generate(), 'Inativo', $slug);
    $plan->deactivate();

    return $plan;
}

function buildRegisterDTO(array $overrides = []): RegisterTenantDTO
{
    return new RegisterTenantDTO(
        slug: $overrides['slug'] ?? 'condo-solar',
        name: $overrides['name'] ?? 'Condomínio Solar',
        type: $overrides['type'] ?? 'vertical',
        adminName: $overrides['adminName'] ?? 'João Silva',
        adminEmail: $overrides['adminEmail'] ?? 'joao@test.com',
        adminPassword: $overrides['adminPassword'] ?? 'secret1234',
        adminPhone: $overrides['adminPhone'] ?? '11999999999',
        planSlug: $overrides['planSlug'] ?? 'basico',
    );
}

function buildRegisterTenantUseCase(
    ?TenantRepositoryInterface $tenantRepo = null,
    ?PendingRegistrationRepositoryInterface $pendingRepo = null,
    ?PlanRepositoryInterface $planRepo = null,
    ?PasswordHasherInterface $hasher = null,
    ?NotificationServiceInterface $notification = null,
): RegisterTenant {
    return new RegisterTenant(
        $tenantRepo ?? registerTenantMockTenantRepo(),
        $pendingRepo ?? registerTenantMockPendingRepo(),
        $planRepo ?? registerTenantMockPlanRepo(buildActivePlan()),
        $hasher ?? registerTenantMockHasher(),
        $notification ?? registerTenantMockNotification(),
    );
}

test('creates pending registration successfully', function () {
    $useCase = buildRegisterTenantUseCase();
    $result = $useCase->execute(buildRegisterDTO());

    expect($result)->toBeInstanceOf(PendingRegistrationDTO::class)
        ->and($result->slug)->toBe('condo-solar')
        ->and($result->name)->toBe('Condomínio Solar')
        ->and($result->type)->toBe('vertical')
        ->and($result->adminName)->toBe('João Silva')
        ->and($result->adminEmail)->toBe('joao@test.com')
        ->and($result->planSlug)->toBe('basico')
        ->and($result->expiresAt)->not->toBeEmpty();
});

test('saves pending registration to repository', function () {
    $pendingRepo = Mockery::mock(PendingRegistrationRepositoryInterface::class);
    $pendingRepo->shouldReceive('findActiveBySlug')->andReturnNull();
    $pendingRepo->shouldReceive('save')
        ->once()
        ->withArgs(function (
            Uuid $id,
            string $slug,
            string $name,
            string $type,
            string $adminName,
            string $adminEmail,
            string $adminPasswordHash,
            ?string $adminPhone,
            string $planSlug,
            string $verificationTokenHash,
            DateTimeImmutable $expiresAt,
        ) {
            return $slug === 'condo-solar'
                && $adminEmail === 'joao@test.com'
                && $adminPasswordHash === '$2y$10$hashed_password'
                && strlen($verificationTokenHash) === 64
                && $expiresAt > new DateTimeImmutable;
        });

    $useCase = buildRegisterTenantUseCase(pendingRepo: $pendingRepo);
    $useCase->execute(buildRegisterDTO());
});

test('sends verification email on registration', function () {
    $notification = Mockery::mock(NotificationServiceInterface::class);
    $notification->shouldReceive('send')
        ->once()
        ->withArgs(fn ($channel, $to, $template, $data) => $channel === 'email'
            && $to === 'joao@test.com'
            && $template === 'tenant-verification'
            && isset($data['verification_token'])
            && isset($data['admin_name'])
            && $data['admin_name'] === 'João Silva'
            && $data['condominium_name'] === 'Condomínio Solar',
        );

    $useCase = buildRegisterTenantUseCase(notification: $notification);
    $useCase->execute(buildRegisterDTO());
});

test('hashes password before saving', function () {
    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')
        ->once()
        ->with('secret1234')
        ->andReturn('$2y$10$hashed');

    $useCase = buildRegisterTenantUseCase(hasher: $hasher);
    $useCase->execute(buildRegisterDTO());
});

test('throws when slug already exists as tenant', function () {
    $existingTenant = Tenant::create(
        Uuid::generate(),
        'condo-solar',
        'Outro Condomínio',
        CondominiumType::Vertical,
    );

    $tenantRepo = registerTenantMockTenantRepo($existingTenant);

    $useCase = buildRegisterTenantUseCase(tenantRepo: $tenantRepo);
    $useCase->execute(buildRegisterDTO());
})->throws(DomainException::class, "Tenant with slug 'condo-solar' already exists");

test('slug duplicate throws with correct error code', function () {
    $existingTenant = Tenant::create(
        Uuid::generate(),
        'condo-solar',
        'Existente',
        CondominiumType::Horizontal,
    );

    $tenantRepo = registerTenantMockTenantRepo($existingTenant);
    $useCase = buildRegisterTenantUseCase(tenantRepo: $tenantRepo);

    try {
        $useCase->execute(buildRegisterDTO());
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('TENANT_SLUG_ALREADY_EXISTS')
            ->and($e->context())->toHaveKey('slug', 'condo-solar');
    }
});

test('throws when slug is pending verification', function () {
    $pendingRepo = Mockery::mock(PendingRegistrationRepositoryInterface::class);
    $pendingRepo->shouldReceive('findActiveBySlug')->andReturn([
        'id' => Uuid::generate()->value(),
        'slug' => 'condo-solar',
    ]);
    $pendingRepo->shouldReceive('save')->never();

    $useCase = buildRegisterTenantUseCase(pendingRepo: $pendingRepo);
    $useCase->execute(buildRegisterDTO());
})->throws(DomainException::class, "A registration for slug 'condo-solar' is already pending verification");

test('pending slug throws with correct error code', function () {
    $pendingRepo = Mockery::mock(PendingRegistrationRepositoryInterface::class);
    $pendingRepo->shouldReceive('findActiveBySlug')->andReturn([
        'id' => Uuid::generate()->value(),
        'slug' => 'condo-solar',
    ]);

    $useCase = buildRegisterTenantUseCase(pendingRepo: $pendingRepo);

    try {
        $useCase->execute(buildRegisterDTO());
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('REGISTRATION_SLUG_PENDING');
    }
});

test('throws on invalid condominium type', function () {
    $useCase = buildRegisterTenantUseCase();
    $useCase->execute(buildRegisterDTO(['type' => 'invalid_type']));
})->throws(DomainException::class, "Invalid condominium type: 'invalid_type'");

test('invalid type throws with correct error code', function () {
    $useCase = buildRegisterTenantUseCase();

    try {
        $useCase->execute(buildRegisterDTO(['type' => 'townhouse']));
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVALID_CONDOMINIUM_TYPE')
            ->and($e->context())->toHaveKey('type', 'townhouse')
            ->and($e->context())->toHaveKey('allowed');
    }
});

test('throws when plan does not exist', function () {
    $useCase = buildRegisterTenantUseCase(planRepo: registerTenantMockPlanRepo(null));
    $useCase->execute(buildRegisterDTO(['planSlug' => 'inexistente']));
})->throws(DomainException::class, "Plan 'inexistente' is not available");

test('throws when plan is inactive', function () {
    $useCase = buildRegisterTenantUseCase(planRepo: registerTenantMockPlanRepo(buildInactivePlan()));
    $useCase->execute(buildRegisterDTO(['planSlug' => 'inativo']));
})->throws(DomainException::class, "Plan 'inativo' is not available");

test('plan not available throws with correct error code', function () {
    $useCase = buildRegisterTenantUseCase(planRepo: registerTenantMockPlanRepo(null));

    try {
        $useCase->execute(buildRegisterDTO(['planSlug' => 'premium']));
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('PLAN_NOT_AVAILABLE')
            ->and($e->context())->toHaveKey('plan_slug', 'premium');
    }
});
