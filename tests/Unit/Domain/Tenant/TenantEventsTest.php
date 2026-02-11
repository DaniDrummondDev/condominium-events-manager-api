<?php

declare(strict_types=1);

use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Events\TenantCreated;
use Domain\Tenant\Events\TenantProvisioned;
use Domain\Tenant\Events\TenantSuspended;

// --- TenantCreated ---

test('TenantCreated implements DomainEvent', function () {
    $event = new TenantCreated(
        tenantId: Uuid::generate(),
        slug: 'test-condo',
        name: 'Test Condominium',
        type: 'vertical',
        occurredAt: new DateTimeImmutable,
    );

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('TenantCreated has correct event name', function () {
    $event = new TenantCreated(
        tenantId: Uuid::generate(),
        slug: 'test-condo',
        name: 'Test Condominium',
        type: 'vertical',
        occurredAt: new DateTimeImmutable,
    );

    expect($event->eventName())->toBe('tenant.created');
});

test('TenantCreated returns correct aggregate ID and payload', function () {
    $id = Uuid::generate();
    $event = new TenantCreated(
        tenantId: $id,
        slug: 'test-condo',
        name: 'Test Condominium',
        type: 'vertical',
        occurredAt: new DateTimeImmutable,
    );

    expect($event->aggregateId()->equals($id))->toBeTrue()
        ->and($event->payload())->toBe([
            'tenant_id' => $id->value(),
            'slug' => 'test-condo',
            'name' => 'Test Condominium',
            'type' => 'vertical',
        ]);
});

// --- TenantProvisioned ---

test('TenantProvisioned has correct event name and payload', function () {
    $id = Uuid::generate();
    $event = new TenantProvisioned(
        tenantId: $id,
        databaseName: 'tenant_test_condo',
        occurredAt: new DateTimeImmutable,
    );

    expect($event->eventName())->toBe('tenant.provisioned')
        ->and($event->aggregateId()->equals($id))->toBeTrue()
        ->and($event->payload())->toBe([
            'tenant_id' => $id->value(),
            'database_name' => 'tenant_test_condo',
        ]);
});

// --- TenantSuspended ---

test('TenantSuspended has correct event name and payload', function () {
    $id = Uuid::generate();
    $event = new TenantSuspended(
        tenantId: $id,
        reason: 'Payment overdue',
        occurredAt: new DateTimeImmutable,
    );

    expect($event->eventName())->toBe('tenant.suspended')
        ->and($event->aggregateId()->equals($id))->toBeTrue()
        ->and($event->payload())->toBe([
            'tenant_id' => $id->value(),
            'reason' => 'Payment overdue',
        ]);
});

// --- occurredAt ---

test('all events preserve occurredAt', function () {
    $now = new DateTimeImmutable('2026-01-15 10:30:00');
    $id = Uuid::generate();

    $created = new TenantCreated($id, 'slug', 'Name', 'vertical', $now);
    $provisioned = new TenantProvisioned($id, 'tenant_slug', $now);
    $suspended = new TenantSuspended($id, 'reason', $now);

    expect($created->occurredAt())->toBe($now)
        ->and($provisioned->occurredAt())->toBe($now)
        ->and($suspended->occurredAt())->toBe($now);
});
