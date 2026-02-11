<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Platform\Repositories\EloquentTenantRepository;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Entities\Tenant;
use Domain\Tenant\Enums\CondominiumType;
use Domain\Tenant\Enums\TenantStatus;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();
    $this->repository = new EloquentTenantRepository;
});

// --- save & findById ---

test('saves and retrieves tenant by ID', function () {
    $id = Uuid::generate();
    $tenant = Tenant::create($id, 'condo-alpha', 'Condomínio Alpha', CondominiumType::Vertical);
    $tenant->startProvisioning();

    $this->repository->save($tenant);

    $found = $this->repository->findById($id);

    expect($found)->not->toBeNull()
        ->and($found->id()->value())->toBe($id->value())
        ->and($found->slug())->toBe('condo-alpha')
        ->and($found->name())->toBe('Condomínio Alpha')
        ->and($found->type())->toBe(CondominiumType::Vertical)
        ->and($found->status())->toBe(TenantStatus::Provisioning)
        ->and($found->databaseName())->toBe('tenant_condo-alpha');
});

// --- findBySlug ---

test('finds tenant by slug', function () {
    $tenant = Tenant::create(Uuid::generate(), 'condo-beta', 'Condomínio Beta', CondominiumType::Horizontal);
    $this->repository->save($tenant);

    $found = $this->repository->findBySlug('condo-beta');

    expect($found)->not->toBeNull()
        ->and($found->slug())->toBe('condo-beta')
        ->and($found->type())->toBe(CondominiumType::Horizontal);
});

test('returns null when slug not found', function () {
    $found = $this->repository->findBySlug('nonexistent');

    expect($found)->toBeNull();
});

test('returns null when ID not found', function () {
    $found = $this->repository->findById(Uuid::generate());

    expect($found)->toBeNull();
});

// --- save updates existing ---

test('updates existing tenant on save', function () {
    $id = Uuid::generate();
    $tenant = new Tenant(
        id: $id,
        slug: 'condo-update',
        name: 'Original Name',
        type: CondominiumType::Mixed,
        status: TenantStatus::Prospect,
    );

    $this->repository->save($tenant);

    // Mutate and save again
    $tenant->rename('Updated Name');
    $tenant->startProvisioning();
    $this->repository->save($tenant);

    $found = $this->repository->findById($id);

    expect($found->name())->toBe('Updated Name')
        ->and($found->status())->toBe(TenantStatus::Provisioning)
        ->and($found->databaseName())->toBe('tenant_condo-update');
});

// --- findAllActive ---

test('finds all active tenants', function () {
    // Create tenants with different statuses
    $activeTenant = new Tenant(
        Uuid::generate(), 'active-1', 'Active 1', CondominiumType::Vertical, TenantStatus::Active, 'tenant_active_1',
    );
    $suspendedTenant = new Tenant(
        Uuid::generate(), 'suspended-1', 'Suspended 1', CondominiumType::Vertical, TenantStatus::Suspended, 'tenant_suspended_1',
    );
    $activeTwo = new Tenant(
        Uuid::generate(), 'active-2', 'Active 2', CondominiumType::Horizontal, TenantStatus::Active, 'tenant_active_2',
    );

    $this->repository->save($activeTenant);
    $this->repository->save($suspendedTenant);
    $this->repository->save($activeTwo);

    $active = $this->repository->findAllActive();

    expect($active)->toHaveCount(2)
        ->and(array_map(fn (Tenant $t) => $t->slug(), $active))->toContain('active-1', 'active-2');
});

// --- findAllForMigration ---

test('finds all tenants eligible for migration', function () {
    // Active with DB
    $t1 = new Tenant(Uuid::generate(), 'mig-active', 'Active', CondominiumType::Vertical, TenantStatus::Active, 'tenant_mig_active');
    // Trial with DB
    $t2 = new Tenant(Uuid::generate(), 'mig-trial', 'Trial', CondominiumType::Horizontal, TenantStatus::Trial, 'tenant_mig_trial');
    // PastDue with DB
    $t3 = new Tenant(Uuid::generate(), 'mig-pastdue', 'PastDue', CondominiumType::Mixed, TenantStatus::PastDue, 'tenant_mig_pastdue');
    // Active without DB (should NOT be included)
    $t4 = new Tenant(Uuid::generate(), 'mig-nodb', 'NoDb', CondominiumType::Vertical, TenantStatus::Active);
    // Suspended with DB (should NOT be included)
    $t5 = new Tenant(Uuid::generate(), 'mig-suspended', 'Suspended', CondominiumType::Vertical, TenantStatus::Suspended, 'tenant_mig_suspended');

    $this->repository->save($t1);
    $this->repository->save($t2);
    $this->repository->save($t3);
    $this->repository->save($t4);
    $this->repository->save($t5);

    $eligible = $this->repository->findAllForMigration();

    expect($eligible)->toHaveCount(3);
    $slugs = array_map(fn (Tenant $t) => $t->slug(), $eligible);
    expect($slugs)->toContain('mig-active', 'mig-trial', 'mig-pastdue')
        ->not->toContain('mig-nodb', 'mig-suspended');
});
