<?php

declare(strict_types=1);

use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Enums\SpaceStatus;
use Domain\Space\Enums\SpaceType;
use Domain\Space\Events\SpaceCreated;
use Domain\Space\Events\SpaceDeactivated;

// --- Factory method ---

test('create() factory creates space with Active status and correct properties', function () {
    $id = Uuid::generate();

    $space = Space::create(
        $id,
        'Salão de Festas',
        'Salão principal do condomínio',
        SpaceType::PartyHall,
        50,
        true,
        4,
        30,
        24,
        48,
    );

    expect($space->id())->toBe($id)
        ->and($space->name())->toBe('Salão de Festas')
        ->and($space->description())->toBe('Salão principal do condomínio')
        ->and($space->type())->toBe(SpaceType::PartyHall)
        ->and($space->status())->toBe(SpaceStatus::Active)
        ->and($space->capacity())->toBe(50)
        ->and($space->requiresApproval())->toBeTrue()
        ->and($space->maxDurationHours())->toBe(4)
        ->and($space->maxAdvanceDays())->toBe(30)
        ->and($space->minAdvanceHours())->toBe(24)
        ->and($space->cancellationDeadlineHours())->toBe(48)
        ->and($space->isActive())->toBeTrue()
        ->and($space->canAcceptReservations())->toBeTrue()
        ->and($space->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('create() factory emits SpaceCreated event', function () {
    $id = Uuid::generate();

    $space = Space::create(
        $id,
        'Piscina',
        null,
        SpaceType::Pool,
        30,
        false,
        null,
        15,
        12,
        24,
    );
    $events = $space->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(SpaceCreated::class)
        ->and($events[0]->spaceId)->toBe($id->value())
        ->and($events[0]->name)->toBe('Piscina')
        ->and($events[0]->type)->toBe('pool');
});

test('create() with null description and null maxDurationHours', function () {
    $id = Uuid::generate();

    $space = Space::create(
        $id,
        'Academia',
        null,
        SpaceType::Gym,
        20,
        false,
        null,
        30,
        1,
        12,
    );

    expect($space->description())->toBeNull()
        ->and($space->maxDurationHours())->toBeNull();
});

// --- Status changes ---

test('deactivate() changes status to Inactive', function () {
    $space = Space::create(
        Uuid::generate(),
        'Churrasqueira',
        null,
        SpaceType::Bbq,
        20,
        false,
        null,
        15,
        12,
        24,
    );
    $space->pullDomainEvents();

    $space->deactivate();

    expect($space->status())->toBe(SpaceStatus::Inactive)
        ->and($space->isActive())->toBeFalse()
        ->and($space->canAcceptReservations())->toBeFalse();
});

test('deactivate() emits SpaceDeactivated event', function () {
    $id = Uuid::generate();
    $space = Space::create(
        $id,
        'Churrasqueira',
        null,
        SpaceType::Bbq,
        20,
        false,
        null,
        15,
        12,
        24,
    );
    $space->pullDomainEvents();

    $space->deactivate();
    $events = $space->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(SpaceDeactivated::class)
        ->and($events[0]->spaceId)->toBe($id->value());
});

test('deactivate() is idempotent when already Inactive', function () {
    $space = Space::create(
        Uuid::generate(),
        'Churrasqueira',
        null,
        SpaceType::Bbq,
        20,
        false,
        null,
        15,
        12,
        24,
    );
    $space->pullDomainEvents();

    $space->deactivate();
    $space->pullDomainEvents();

    $space->deactivate();
    $events = $space->pullDomainEvents();

    expect($space->status())->toBe(SpaceStatus::Inactive)
        ->and($events)->toBeEmpty();
});

test('activate() sets status to Active', function () {
    $space = Space::create(
        Uuid::generate(),
        'Quadra',
        null,
        SpaceType::SportsCourt,
        10,
        false,
        null,
        30,
        12,
        24,
    );
    $space->pullDomainEvents();

    $space->deactivate();
    $space->activate();

    expect($space->status())->toBe(SpaceStatus::Active)
        ->and($space->isActive())->toBeTrue();
});

test('setMaintenance() changes status to Maintenance', function () {
    $space = Space::create(
        Uuid::generate(),
        'Piscina',
        null,
        SpaceType::Pool,
        30,
        false,
        null,
        15,
        12,
        24,
    );
    $space->pullDomainEvents();

    $space->setMaintenance();

    expect($space->status())->toBe(SpaceStatus::Maintenance)
        ->and($space->isActive())->toBeFalse()
        ->and($space->canAcceptReservations())->toBeFalse();
});

// --- Property updates ---

test('updateName() changes the name', function () {
    $space = Space::create(Uuid::generate(), 'Salão', null, SpaceType::PartyHall, 50, true, 4, 30, 24, 48);
    $space->pullDomainEvents();

    $space->updateName('Salão Principal');

    expect($space->name())->toBe('Salão Principal');
});

test('updateDescription() changes the description', function () {
    $space = Space::create(Uuid::generate(), 'Salão', null, SpaceType::PartyHall, 50, true, 4, 30, 24, 48);
    $space->pullDomainEvents();

    $space->updateDescription('Nova descrição');

    expect($space->description())->toBe('Nova descrição');
});

test('updateDescription() accepts null', function () {
    $space = Space::create(Uuid::generate(), 'Salão', 'Descrição', SpaceType::PartyHall, 50, true, 4, 30, 24, 48);
    $space->pullDomainEvents();

    $space->updateDescription(null);

    expect($space->description())->toBeNull();
});

test('updateType() changes the type', function () {
    $space = Space::create(Uuid::generate(), 'Salão', null, SpaceType::PartyHall, 50, true, 4, 30, 24, 48);
    $space->pullDomainEvents();

    $space->updateType(SpaceType::MeetingRoom);

    expect($space->type())->toBe(SpaceType::MeetingRoom);
});

test('updateCapacity() changes the capacity', function () {
    $space = Space::create(Uuid::generate(), 'Salão', null, SpaceType::PartyHall, 50, true, 4, 30, 24, 48);
    $space->pullDomainEvents();

    $space->updateCapacity(100);

    expect($space->capacity())->toBe(100);
});

test('updateRequiresApproval() changes the requiresApproval flag', function () {
    $space = Space::create(Uuid::generate(), 'Salão', null, SpaceType::PartyHall, 50, true, 4, 30, 24, 48);
    $space->pullDomainEvents();

    $space->updateRequiresApproval(false);

    expect($space->requiresApproval())->toBeFalse();
});

test('updateMaxDurationHours() changes the maxDurationHours', function () {
    $space = Space::create(Uuid::generate(), 'Salão', null, SpaceType::PartyHall, 50, true, 4, 30, 24, 48);
    $space->pullDomainEvents();

    $space->updateMaxDurationHours(8);

    expect($space->maxDurationHours())->toBe(8);
});

test('updateMaxDurationHours() accepts null', function () {
    $space = Space::create(Uuid::generate(), 'Salão', null, SpaceType::PartyHall, 50, true, 4, 30, 24, 48);
    $space->pullDomainEvents();

    $space->updateMaxDurationHours(null);

    expect($space->maxDurationHours())->toBeNull();
});

test('updateMaxAdvanceDays() changes the maxAdvanceDays', function () {
    $space = Space::create(Uuid::generate(), 'Salão', null, SpaceType::PartyHall, 50, true, 4, 30, 24, 48);
    $space->pullDomainEvents();

    $space->updateMaxAdvanceDays(60);

    expect($space->maxAdvanceDays())->toBe(60);
});

test('updateMinAdvanceHours() changes the minAdvanceHours', function () {
    $space = Space::create(Uuid::generate(), 'Salão', null, SpaceType::PartyHall, 50, true, 4, 30, 24, 48);
    $space->pullDomainEvents();

    $space->updateMinAdvanceHours(48);

    expect($space->minAdvanceHours())->toBe(48);
});

test('updateCancellationDeadlineHours() changes the cancellationDeadlineHours', function () {
    $space = Space::create(Uuid::generate(), 'Salão', null, SpaceType::PartyHall, 50, true, 4, 30, 24, 48);
    $space->pullDomainEvents();

    $space->updateCancellationDeadlineHours(72);

    expect($space->cancellationDeadlineHours())->toBe(72);
});

// --- Domain events ---

test('pullDomainEvents() returns and clears events', function () {
    $space = Space::create(Uuid::generate(), 'Salão', null, SpaceType::PartyHall, 50, true, 4, 30, 24, 48);

    $events = $space->pullDomainEvents();
    expect($events)->toHaveCount(1);

    $eventsAfterPull = $space->pullDomainEvents();
    expect($eventsAfterPull)->toBeEmpty();
});
