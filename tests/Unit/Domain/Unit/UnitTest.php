<?php

declare(strict_types=1);

use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Unit;
use Domain\Unit\Enums\UnitStatus;
use Domain\Unit\Enums\UnitType;
use Domain\Unit\Events\UnitCreated;
use Domain\Unit\Events\UnitDeactivated;

// --- Factory method ---

test('create() factory creates unit with Active status and not occupied', function () {
    $id = Uuid::generate();
    $blockId = Uuid::generate();

    $unit = Unit::create($id, $blockId, '101', 1, UnitType::Apartment);

    expect($unit->id())->toBe($id)
        ->and($unit->blockId())->toBe($blockId)
        ->and($unit->number())->toBe('101')
        ->and($unit->floor())->toBe(1)
        ->and($unit->type())->toBe(UnitType::Apartment)
        ->and($unit->status())->toBe(UnitStatus::Active)
        ->and($unit->isOccupied())->toBeFalse()
        ->and($unit->isActive())->toBeTrue()
        ->and($unit->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('create() factory emits UnitCreated event', function () {
    $id = Uuid::generate();
    $blockId = Uuid::generate();

    $unit = Unit::create($id, $blockId, '101', 1, UnitType::Apartment);
    $events = $unit->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(UnitCreated::class)
        ->and($events[0]->unitId)->toBe($id->value())
        ->and($events[0]->blockId)->toBe($blockId->value());
});

test('create() with null blockId for horizontal condos', function () {
    $id = Uuid::generate();

    $unit = Unit::create($id, null, '10', null, UnitType::House);

    expect($unit->blockId())->toBeNull()
        ->and($unit->floor())->toBeNull();
});

test('create() with null blockId emits UnitCreated event with null blockId', function () {
    $id = Uuid::generate();

    $unit = Unit::create($id, null, '10', null, UnitType::House);
    $events = $unit->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(UnitCreated::class)
        ->and($events[0]->blockId)->toBeNull();
});

// --- Property updates ---

test('updateNumber() changes the number', function () {
    $unit = Unit::create(Uuid::generate(), Uuid::generate(), '101', 1, UnitType::Apartment);
    $unit->pullDomainEvents();

    $unit->updateNumber('201');

    expect($unit->number())->toBe('201');
});

test('updateFloor() changes the floor', function () {
    $unit = Unit::create(Uuid::generate(), Uuid::generate(), '101', 1, UnitType::Apartment);
    $unit->pullDomainEvents();

    $unit->updateFloor(5);

    expect($unit->floor())->toBe(5);
});

test('updateFloor() accepts null', function () {
    $unit = Unit::create(Uuid::generate(), Uuid::generate(), '101', 1, UnitType::Apartment);
    $unit->pullDomainEvents();

    $unit->updateFloor(null);

    expect($unit->floor())->toBeNull();
});

test('updateType() changes the type', function () {
    $unit = Unit::create(Uuid::generate(), Uuid::generate(), '101', 1, UnitType::Apartment);
    $unit->pullDomainEvents();

    $unit->updateType(UnitType::Store);

    expect($unit->type())->toBe(UnitType::Store);
});

// --- Status changes ---

test('activate() sets status to Active', function () {
    $unit = Unit::create(Uuid::generate(), Uuid::generate(), '101', 1, UnitType::Apartment);
    $unit->pullDomainEvents();

    $unit->deactivate();
    $unit->activate();

    expect($unit->status())->toBe(UnitStatus::Active)
        ->and($unit->isActive())->toBeTrue();
});

test('deactivate() sets status to Inactive', function () {
    $unit = Unit::create(Uuid::generate(), Uuid::generate(), '101', 1, UnitType::Apartment);
    $unit->pullDomainEvents();

    $unit->deactivate();

    expect($unit->status())->toBe(UnitStatus::Inactive)
        ->and($unit->isActive())->toBeFalse();
});

test('deactivate() emits UnitDeactivated event', function () {
    $id = Uuid::generate();
    $unit = Unit::create($id, Uuid::generate(), '101', 1, UnitType::Apartment);
    $unit->pullDomainEvents();

    $unit->deactivate();
    $events = $unit->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(UnitDeactivated::class)
        ->and($events[0]->unitId)->toBe($id->value());
});

test('deactivate() is idempotent when already Inactive', function () {
    $unit = Unit::create(Uuid::generate(), Uuid::generate(), '101', 1, UnitType::Apartment);
    $unit->pullDomainEvents();

    $unit->deactivate();
    $unit->pullDomainEvents();

    $unit->deactivate();
    $events = $unit->pullDomainEvents();

    expect($unit->status())->toBe(UnitStatus::Inactive)
        ->and($events)->toBeEmpty();
});

// --- Occupancy ---

test('markOccupied() on active unit sets isOccupied true', function () {
    $unit = Unit::create(Uuid::generate(), Uuid::generate(), '101', 1, UnitType::Apartment);
    $unit->pullDomainEvents();

    $unit->markOccupied();

    expect($unit->isOccupied())->toBeTrue();
});

test('markOccupied() on inactive unit throws DomainException with UNIT_INACTIVE code', function () {
    $unit = Unit::create(Uuid::generate(), Uuid::generate(), '101', 1, UnitType::Apartment);
    $unit->pullDomainEvents();
    $unit->deactivate();

    try {
        $unit->markOccupied();
        test()->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('UNIT_INACTIVE')
            ->and($e->context())->toHaveKey('unit_id');
    }
});

test('markVacant() sets isOccupied false', function () {
    $unit = Unit::create(Uuid::generate(), Uuid::generate(), '101', 1, UnitType::Apartment);
    $unit->pullDomainEvents();

    $unit->markOccupied();
    expect($unit->isOccupied())->toBeTrue();

    $unit->markVacant();
    expect($unit->isOccupied())->toBeFalse();
});

// --- Domain events ---

test('pullDomainEvents() returns and clears events', function () {
    $unit = Unit::create(Uuid::generate(), Uuid::generate(), '101', 1, UnitType::Apartment);

    $events = $unit->pullDomainEvents();
    expect($events)->toHaveCount(1);

    $eventsAfterPull = $unit->pullDomainEvents();
    expect($eventsAfterPull)->toBeEmpty();
});
