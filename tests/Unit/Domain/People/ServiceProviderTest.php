<?php

declare(strict_types=1);

use Domain\People\Entities\ServiceProvider;
use Domain\People\Enums\ServiceProviderStatus;
use Domain\People\Enums\ServiceType;
use Domain\Shared\ValueObjects\Uuid;

function createServiceProvider(
    ?Uuid $id = null,
    ?string $companyName = 'Buffet Gourmet',
    string $name = 'Carlos Souza',
    string $document = '12345678000199',
    ?string $phone = '11999999999',
    ServiceType $serviceType = ServiceType::Buffet,
    ?string $notes = null,
    ?Uuid $createdBy = null,
): ServiceProvider {
    return ServiceProvider::create(
        $id ?? Uuid::generate(),
        $companyName,
        $name,
        $document,
        $phone,
        $serviceType,
        $notes,
        $createdBy ?? Uuid::generate(),
    );
}

// ── Factory: create() ──────────────────────────────────────────

test('create() sets Active status', function () {
    $provider = createServiceProvider();

    expect($provider->status())->toBe(ServiceProviderStatus::Active);
});

test('create() sets all properties correctly', function () {
    $id = Uuid::generate();
    $createdBy = Uuid::generate();

    $provider = createServiceProvider(
        id: $id,
        companyName: 'Empresa X',
        name: 'José',
        document: '99999999000100',
        phone: '11888888888',
        serviceType: ServiceType::Cleaning,
        notes: 'Contrato anual',
        createdBy: $createdBy,
    );

    expect($provider->id())->toBe($id)
        ->and($provider->companyName())->toBe('Empresa X')
        ->and($provider->name())->toBe('José')
        ->and($provider->document())->toBe('99999999000100')
        ->and($provider->phone())->toBe('11888888888')
        ->and($provider->serviceType())->toBe(ServiceType::Cleaning)
        ->and($provider->notes())->toBe('Contrato anual')
        ->and($provider->createdBy())->toBe($createdBy)
        ->and($provider->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('create() with null optional fields', function () {
    $provider = createServiceProvider(companyName: null, phone: null, notes: null);

    expect($provider->companyName())->toBeNull()
        ->and($provider->phone())->toBeNull()
        ->and($provider->notes())->toBeNull();
});

// ── deactivate() ───────────────────────────────────────────────

test('deactivate() sets Inactive status', function () {
    $provider = createServiceProvider();

    $provider->deactivate();

    expect($provider->status())->toBe(ServiceProviderStatus::Inactive);
});

// ── block() ────────────────────────────────────────────────────

test('block() sets Blocked status', function () {
    $provider = createServiceProvider();

    $provider->block();

    expect($provider->status())->toBe(ServiceProviderStatus::Blocked);
});

// ── activate() ─────────────────────────────────────────────────

test('activate() sets Active status from Inactive', function () {
    $provider = createServiceProvider();
    $provider->deactivate();

    $provider->activate();

    expect($provider->status())->toBe(ServiceProviderStatus::Active);
});

test('activate() sets Active status from Blocked', function () {
    $provider = createServiceProvider();
    $provider->block();

    $provider->activate();

    expect($provider->status())->toBe(ServiceProviderStatus::Active);
});

// ── update() ───────────────────────────────────────────────────

test('update() changes mutable fields', function () {
    $provider = createServiceProvider();

    $provider->update(
        companyName: 'Nova Empresa',
        name: 'Novo Nome',
        phone: '11777777777',
        serviceType: ServiceType::Security,
        notes: 'Notas atualizadas',
    );

    expect($provider->companyName())->toBe('Nova Empresa')
        ->and($provider->name())->toBe('Novo Nome')
        ->and($provider->phone())->toBe('11777777777')
        ->and($provider->serviceType())->toBe(ServiceType::Security)
        ->and($provider->notes())->toBe('Notas atualizadas');
});

test('update() does not change immutable fields', function () {
    $id = Uuid::generate();
    $createdBy = Uuid::generate();
    $provider = createServiceProvider(id: $id, document: '11111111000111', createdBy: $createdBy);

    $provider->update('X', 'Y', null, ServiceType::Other, null);

    expect($provider->id())->toBe($id)
        ->and($provider->document())->toBe('11111111000111')
        ->and($provider->createdBy())->toBe($createdBy);
});

// ── Business Logic ─────────────────────────────────────────────

test('isActive() returns true for Active provider', function () {
    $provider = createServiceProvider();

    expect($provider->isActive())->toBeTrue();
});

test('isActive() returns false for Inactive provider', function () {
    $provider = createServiceProvider();
    $provider->deactivate();

    expect($provider->isActive())->toBeFalse();
});

test('canBeLinkedToVisits() returns true for Active provider', function () {
    $provider = createServiceProvider();

    expect($provider->canBeLinkedToVisits())->toBeTrue();
});

test('canBeLinkedToVisits() returns false for Blocked provider', function () {
    $provider = createServiceProvider();
    $provider->block();

    expect($provider->canBeLinkedToVisits())->toBeFalse();
});

// ── pullDomainEvents() ─────────────────────────────────────────

test('pullDomainEvents() returns empty array for new provider', function () {
    $provider = createServiceProvider();

    expect($provider->pullDomainEvents())->toHaveCount(0);
});
