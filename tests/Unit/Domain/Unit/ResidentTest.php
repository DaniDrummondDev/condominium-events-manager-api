<?php

declare(strict_types=1);

use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Resident;
use Domain\Unit\Enums\ResidentRole;
use Domain\Unit\Enums\ResidentStatus;
use Domain\Unit\Events\ResidentActivated;
use Domain\Unit\Events\ResidentDeactivated;
use Domain\Unit\Events\ResidentInvited;
use Domain\Unit\Events\ResidentMovedOut;

// --- Helper ---

function createResident(ResidentStatus $status = ResidentStatus::Active): Resident
{
    if ($status === ResidentStatus::Invited) {
        return Resident::createInvited(
            id: Uuid::generate(),
            unitId: Uuid::generate(),
            tenantUserId: Uuid::generate(),
            name: 'João Silva',
            email: 'joao@example.com',
            phone: '+5511999999999',
            roleInUnit: ResidentRole::Owner,
            isPrimary: true,
        );
    }

    return Resident::create(
        id: Uuid::generate(),
        unitId: Uuid::generate(),
        tenantUserId: Uuid::generate(),
        name: 'João Silva',
        email: 'joao@example.com',
        phone: '+5511999999999',
        roleInUnit: ResidentRole::Owner,
        isPrimary: true,
    );
}

// --- create() factory ---

test('create() factory creates resident with Active status', function () {
    $id = Uuid::generate();
    $unitId = Uuid::generate();
    $tenantUserId = Uuid::generate();

    $resident = Resident::create(
        $id, $unitId, $tenantUserId,
        'Maria Santos', 'maria@example.com', '+5511988888888',
        ResidentRole::Owner, true,
    );

    expect($resident->id())->toBe($id)
        ->and($resident->unitId())->toBe($unitId)
        ->and($resident->tenantUserId())->toBe($tenantUserId)
        ->and($resident->name())->toBe('Maria Santos')
        ->and($resident->email())->toBe('maria@example.com')
        ->and($resident->phone())->toBe('+5511988888888')
        ->and($resident->roleInUnit())->toBe(ResidentRole::Owner)
        ->and($resident->isPrimary())->toBeTrue()
        ->and($resident->status())->toBe(ResidentStatus::Active)
        ->and($resident->movedInAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($resident->movedOutAt())->toBeNull()
        ->and($resident->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('create() factory does not emit any domain events', function () {
    $resident = Resident::create(
        Uuid::generate(), Uuid::generate(), Uuid::generate(),
        'João Silva', 'joao@example.com', null,
        ResidentRole::TenantResident, false,
    );

    expect($resident->pullDomainEvents())->toBeEmpty();
});

test('create() with null phone', function () {
    $resident = Resident::create(
        Uuid::generate(), Uuid::generate(), Uuid::generate(),
        'Ana Costa', 'ana@example.com', null,
        ResidentRole::Dependent, false,
    );

    expect($resident->phone())->toBeNull();
});

// --- createInvited() factory ---

test('createInvited() creates resident with Invited status', function () {
    $id = Uuid::generate();
    $unitId = Uuid::generate();
    $email = 'convidado@example.com';

    $resident = Resident::createInvited(
        $id, $unitId, Uuid::generate(),
        'Pedro Oliveira', $email, null,
        ResidentRole::TenantResident, false,
    );

    expect($resident->status())->toBe(ResidentStatus::Invited)
        ->and($resident->isActive())->toBeFalse();
});

test('createInvited() emits ResidentInvited event', function () {
    $id = Uuid::generate();
    $unitId = Uuid::generate();
    $email = 'convidado@example.com';

    $resident = Resident::createInvited(
        $id, $unitId, Uuid::generate(),
        'Pedro Oliveira', $email, null,
        ResidentRole::TenantResident, false,
    );

    $events = $resident->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ResidentInvited::class)
        ->and($events[0]->residentId)->toBe($id->value())
        ->and($events[0]->unitId)->toBe($unitId->value())
        ->and($events[0]->email)->toBe($email);
});

// --- activate() ---

test('activate() from Invited changes status to Active and emits ResidentActivated', function () {
    $resident = createResident(ResidentStatus::Invited);
    $resident->pullDomainEvents();

    $resident->activate();

    $events = $resident->pullDomainEvents();

    expect($resident->status())->toBe(ResidentStatus::Active)
        ->and($resident->isActive())->toBeTrue()
        ->and($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ResidentActivated::class)
        ->and($events[0]->residentId)->toBe($resident->id()->value())
        ->and($events[0]->tenantUserId)->toBe($resident->tenantUserId()->value());
});

test('activate() when already Active is idempotent', function () {
    $resident = createResident(ResidentStatus::Active);

    $resident->activate();

    expect($resident->status())->toBe(ResidentStatus::Active)
        ->and($resident->pullDomainEvents())->toBeEmpty();
});

test('activate() from Inactive throws DomainException with RESIDENT_INVALID_STATUS code', function () {
    $resident = createResident(ResidentStatus::Active);
    $resident->deactivate();
    $resident->pullDomainEvents();

    try {
        $resident->activate();
        test()->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('RESIDENT_INVALID_STATUS')
            ->and($e->context())->toHaveKey('resident_id')
            ->and($e->context())->toHaveKey('status', 'inactive');
    }
});

// --- deactivate() ---

test('deactivate() sets status to Inactive and emits ResidentDeactivated', function () {
    $resident = createResident(ResidentStatus::Active);

    $resident->deactivate();

    $events = $resident->pullDomainEvents();

    expect($resident->status())->toBe(ResidentStatus::Inactive)
        ->and($resident->isActive())->toBeFalse()
        ->and($resident->movedOutAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ResidentDeactivated::class)
        ->and($events[0]->residentId)->toBe($resident->id()->value())
        ->and($events[0]->unitId)->toBe($resident->unitId()->value());
});

test('deactivate() when already Inactive is idempotent', function () {
    $resident = createResident(ResidentStatus::Active);
    $resident->deactivate();
    $resident->pullDomainEvents();

    $resident->deactivate();

    expect($resident->status())->toBe(ResidentStatus::Inactive)
        ->and($resident->pullDomainEvents())->toBeEmpty();
});

// --- moveOut() ---

test('moveOut() sets movedOutAt and Inactive status, emits ResidentMovedOut', function () {
    $resident = createResident(ResidentStatus::Active);

    $movedOutAt = new DateTimeImmutable('2025-06-15T10:00:00+00:00');
    $resident->moveOut($movedOutAt);

    $events = $resident->pullDomainEvents();

    expect($resident->status())->toBe(ResidentStatus::Inactive)
        ->and($resident->movedOutAt())->toBe($movedOutAt)
        ->and($resident->isActive())->toBeFalse()
        ->and($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ResidentMovedOut::class)
        ->and($events[0]->residentId)->toBe($resident->id()->value())
        ->and($events[0]->unitId)->toBe($resident->unitId()->value())
        ->and($events[0]->movedOutAt)->toBe($movedOutAt->format('c'));
});

test('moveOut() when already moved out throws DomainException with RESIDENT_ALREADY_MOVED_OUT code', function () {
    $resident = createResident(ResidentStatus::Active);
    $resident->moveOut(new DateTimeImmutable);

    try {
        $resident->moveOut(new DateTimeImmutable);
        test()->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('RESIDENT_ALREADY_MOVED_OUT')
            ->and($e->context())->toHaveKey('resident_id');
    }
});

// --- isActive ---

test('isActive() returns true when Active', function () {
    $resident = createResident(ResidentStatus::Active);

    expect($resident->isActive())->toBeTrue();
});

test('isActive() returns false when Inactive', function () {
    $resident = createResident(ResidentStatus::Active);
    $resident->deactivate();

    expect($resident->isActive())->toBeFalse();
});

test('isActive() returns false when Invited', function () {
    $resident = createResident(ResidentStatus::Invited);

    expect($resident->isActive())->toBeFalse();
});

// --- Domain events ---

test('pullDomainEvents() returns and clears events', function () {
    $resident = createResident(ResidentStatus::Invited);

    $events = $resident->pullDomainEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ResidentInvited::class);

    $eventsAfterPull = $resident->pullDomainEvents();
    expect($eventsAfterPull)->toBeEmpty();
});
