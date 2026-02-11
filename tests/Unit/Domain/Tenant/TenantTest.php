<?php

declare(strict_types=1);

use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Entities\Tenant;
use Domain\Tenant\Enums\CondominiumType;
use Domain\Tenant\Enums\TenantStatus;

function createTenant(TenantStatus $status = TenantStatus::Prospect): Tenant
{
    return new Tenant(
        id: Uuid::generate(),
        slug: 'condominio-teste',
        name: 'Condomínio Teste',
        type: CondominiumType::Vertical,
        status: $status,
    );
}

// --- Factory method ---

test('creates tenant with Prospect status via factory', function () {
    $id = Uuid::generate();
    $tenant = Tenant::create($id, 'meu-condo', 'Meu Condomínio', CondominiumType::Horizontal);

    expect($tenant->id())->toBe($id)
        ->and($tenant->slug())->toBe('meu-condo')
        ->and($tenant->name())->toBe('Meu Condomínio')
        ->and($tenant->type())->toBe(CondominiumType::Horizontal)
        ->and($tenant->status())->toBe(TenantStatus::Prospect)
        ->and($tenant->databaseName())->toBeNull();
});

// --- Valid transitions ---

test('starts provisioning from Prospect', function () {
    $tenant = createTenant(TenantStatus::Prospect);

    $tenant->startProvisioning();

    expect($tenant->status())->toBe(TenantStatus::Provisioning)
        ->and($tenant->databaseName())->toBe('tenant_condominio-teste');
});

test('starts trial from Prospect', function () {
    $tenant = createTenant(TenantStatus::Prospect);

    $tenant->startTrial();

    expect($tenant->status())->toBe(TenantStatus::Trial);
});

test('activates from Provisioning', function () {
    $tenant = createTenant(TenantStatus::Provisioning);

    $tenant->activate();

    expect($tenant->status())->toBe(TenantStatus::Active);
});

test('rollbacks provisioning to Prospect', function () {
    $tenant = createTenant(TenantStatus::Prospect);
    $tenant->startProvisioning();

    expect($tenant->databaseName())->toBe('tenant_condominio-teste');

    $tenant->rollbackProvisioning();

    expect($tenant->status())->toBe(TenantStatus::Prospect)
        ->and($tenant->databaseName())->toBeNull();
});

test('suspends from Active', function () {
    $tenant = createTenant(TenantStatus::Active);

    $tenant->suspend();

    expect($tenant->status())->toBe(TenantStatus::Suspended);
});

test('marks past due from Active', function () {
    $tenant = createTenant(TenantStatus::Active);

    $tenant->markPastDue();

    expect($tenant->status())->toBe(TenantStatus::PastDue);
});

test('cancels from Active', function () {
    $tenant = createTenant(TenantStatus::Active);

    $tenant->cancel();

    expect($tenant->status())->toBe(TenantStatus::Canceled);
});

test('cancels from Suspended', function () {
    $tenant = createTenant(TenantStatus::Suspended);

    $tenant->cancel();

    expect($tenant->status())->toBe(TenantStatus::Canceled);
});

test('archives from Canceled', function () {
    $tenant = createTenant(TenantStatus::Canceled);

    $tenant->archive();

    expect($tenant->status())->toBe(TenantStatus::Archived);
});

test('reactivates from Suspended', function () {
    $tenant = createTenant(TenantStatus::Suspended);

    $tenant->reactivate();

    expect($tenant->status())->toBe(TenantStatus::Active);
});

test('reactivates from PastDue', function () {
    $tenant = createTenant(TenantStatus::PastDue);

    $tenant->reactivate();

    expect($tenant->status())->toBe(TenantStatus::Active);
});

// --- Full lifecycle: Prospect → Provisioning → Active → Suspended → Canceled → Archived ---

test('supports full lifecycle flow', function () {
    $tenant = Tenant::create(Uuid::generate(), 'lifecycle', 'Lifecycle Test', CondominiumType::Mixed);

    expect($tenant->status())->toBe(TenantStatus::Prospect);

    $tenant->startProvisioning();
    expect($tenant->status())->toBe(TenantStatus::Provisioning);

    $tenant->activate();
    expect($tenant->status())->toBe(TenantStatus::Active);

    $tenant->suspend();
    expect($tenant->status())->toBe(TenantStatus::Suspended);

    $tenant->cancel();
    expect($tenant->status())->toBe(TenantStatus::Canceled);

    $tenant->archive();
    expect($tenant->status())->toBe(TenantStatus::Archived);
});

// --- Invalid transitions ---

test('cannot activate from Prospect', function () {
    $tenant = createTenant(TenantStatus::Prospect);

    $tenant->activate();
})->throws(DomainException::class);

test('cannot suspend from Prospect', function () {
    $tenant = createTenant(TenantStatus::Prospect);

    $tenant->suspend();
})->throws(DomainException::class);

test('cannot archive from Active', function () {
    $tenant = createTenant(TenantStatus::Active);

    $tenant->archive();
})->throws(DomainException::class);

test('cannot transition from Archived', function () {
    $tenant = createTenant(TenantStatus::Archived);

    $tenant->activate();
})->throws(DomainException::class);

test('cannot start provisioning from Active', function () {
    $tenant = createTenant(TenantStatus::Active);

    $tenant->startProvisioning();
})->throws(DomainException::class);

test('invalid transition throws with correct error code and context', function () {
    $tenant = createTenant(TenantStatus::Prospect);

    try {
        $tenant->activate();
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVALID_TENANT_TRANSITION')
            ->and($e->context())->toHaveKey('current_status', 'prospect')
            ->and($e->context())->toHaveKey('target_status', 'active')
            ->and($e->context())->toHaveKey('allowed');
    }
});

// --- Other behaviors ---

test('rename changes the name', function () {
    $tenant = createTenant();

    $tenant->rename('Novo Nome');

    expect($tenant->name())->toBe('Novo Nome');
});

test('pullDomainEvents returns and clears events', function () {
    $tenant = createTenant();

    // Tenant entity does not record domain events internally (use cases dispatch them)
    // So pullDomainEvents should return empty initially
    $events = $tenant->pullDomainEvents();

    expect($events)->toBeArray()->toBeEmpty();

    // Calling again returns empty too
    expect($tenant->pullDomainEvents())->toBeEmpty();
});
