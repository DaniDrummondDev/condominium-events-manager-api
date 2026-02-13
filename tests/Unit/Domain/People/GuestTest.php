<?php

declare(strict_types=1);

use Domain\People\Entities\Guest;
use Domain\People\Enums\GuestStatus;
use Domain\People\Events\GuestAccessDenied;
use Domain\People\Events\GuestCheckedIn;
use Domain\People\Events\GuestCheckedOut;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

function createGuest(
    ?Uuid $id = null,
    ?Uuid $reservationId = null,
    string $name = 'João Silva',
    ?string $document = '12345678900',
    ?string $phone = '11999999999',
    ?string $vehiclePlate = 'ABC1234',
    ?string $relationship = 'Amigo',
    ?Uuid $registeredBy = null,
): Guest {
    return Guest::create(
        $id ?? Uuid::generate(),
        $reservationId ?? Uuid::generate(),
        $name,
        $document,
        $phone,
        $vehiclePlate,
        $relationship,
        $registeredBy ?? Uuid::generate(),
    );
}

function createCheckedInGuest(): Guest
{
    $guest = createGuest();
    $guest->checkIn(Uuid::generate());
    $guest->pullDomainEvents();

    return $guest;
}

// ── Factory: create() ──────────────────────────────────────────

test('create() sets Registered status', function () {
    $guest = createGuest();

    expect($guest->status())->toBe(GuestStatus::Registered);
});

test('create() sets all properties correctly', function () {
    $id = Uuid::generate();
    $reservationId = Uuid::generate();
    $registeredBy = Uuid::generate();

    $guest = createGuest(
        id: $id,
        reservationId: $reservationId,
        name: 'Maria',
        document: '98765432100',
        phone: '11888888888',
        vehiclePlate: 'XYZ9876',
        relationship: 'Familiar',
        registeredBy: $registeredBy,
    );

    expect($guest->id())->toBe($id)
        ->and($guest->reservationId())->toBe($reservationId)
        ->and($guest->name())->toBe('Maria')
        ->and($guest->document())->toBe('98765432100')
        ->and($guest->phone())->toBe('11888888888')
        ->and($guest->vehiclePlate())->toBe('XYZ9876')
        ->and($guest->relationship())->toBe('Familiar')
        ->and($guest->registeredBy())->toBe($registeredBy)
        ->and($guest->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('create() initializes nullable fields as null', function () {
    $guest = createGuest();

    expect($guest->checkedInAt())->toBeNull()
        ->and($guest->checkedOutAt())->toBeNull()
        ->and($guest->checkedInBy())->toBeNull()
        ->and($guest->deniedBy())->toBeNull()
        ->and($guest->deniedReason())->toBeNull();
});

test('create() with null optional fields', function () {
    $guest = createGuest(document: null, phone: null, vehiclePlate: null, relationship: null);

    expect($guest->document())->toBeNull()
        ->and($guest->phone())->toBeNull()
        ->and($guest->vehiclePlate())->toBeNull()
        ->and($guest->relationship())->toBeNull();
});

// ── checkIn() ──────────────────────────────────────────────────

test('checkIn() transitions Registered to CheckedIn', function () {
    $guest = createGuest();
    $checkedInBy = Uuid::generate();

    $guest->checkIn($checkedInBy);

    expect($guest->status())->toBe(GuestStatus::CheckedIn)
        ->and($guest->checkedInAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($guest->checkedInBy())->toBe($checkedInBy);
});

test('checkIn() emits GuestCheckedIn event', function () {
    $guest = createGuest();
    $checkedInBy = Uuid::generate();

    $guest->checkIn($checkedInBy);
    $events = $guest->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(GuestCheckedIn::class)
        ->and($events[0]->guestId)->toBe($guest->id()->value())
        ->and($events[0]->reservationId)->toBe($guest->reservationId()->value())
        ->and($events[0]->checkedInBy)->toBe($checkedInBy->value());
});

test('checkIn() throws from CheckedOut status', function () {
    $guest = createCheckedInGuest();
    $guest->checkOut(Uuid::generate());

    $guest->checkIn(Uuid::generate());
})->throws(DomainException::class, "Cannot transition guest from 'checked_out' to 'checked_in'");

test('checkIn() throws from Denied status', function () {
    $guest = createGuest();
    $guest->deny(Uuid::generate(), 'Motivo');

    $guest->checkIn(Uuid::generate());
})->throws(DomainException::class);

// ── checkOut() ─────────────────────────────────────────────────

test('checkOut() transitions CheckedIn to CheckedOut', function () {
    $guest = createCheckedInGuest();

    $guest->checkOut(Uuid::generate());

    expect($guest->status())->toBe(GuestStatus::CheckedOut)
        ->and($guest->checkedOutAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('checkOut() emits GuestCheckedOut event', function () {
    $guest = createCheckedInGuest();
    $checkedOutBy = Uuid::generate();

    $guest->checkOut($checkedOutBy);
    $events = $guest->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(GuestCheckedOut::class)
        ->and($events[0]->checkedOutBy)->toBe($checkedOutBy->value());
});

test('checkOut() throws from Registered status', function () {
    $guest = createGuest();

    $guest->checkOut(Uuid::generate());
})->throws(DomainException::class);

// ── deny() ─────────────────────────────────────────────────────

test('deny() transitions Registered to Denied', function () {
    $guest = createGuest();
    $deniedBy = Uuid::generate();

    $guest->deny($deniedBy, 'Documento inválido');

    expect($guest->status())->toBe(GuestStatus::Denied)
        ->and($guest->deniedBy())->toBe($deniedBy)
        ->and($guest->deniedReason())->toBe('Documento inválido');
});

test('deny() emits GuestAccessDenied event', function () {
    $guest = createGuest();
    $deniedBy = Uuid::generate();

    $guest->deny($deniedBy, 'Sem autorização');
    $events = $guest->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(GuestAccessDenied::class)
        ->and($events[0]->deniedBy)->toBe($deniedBy->value())
        ->and($events[0]->reason)->toBe('Sem autorização');
});

test('deny() throws from CheckedIn status', function () {
    $guest = createCheckedInGuest();

    $guest->deny(Uuid::generate(), 'Motivo');
})->throws(DomainException::class);

// ── markAsNoShow() ─────────────────────────────────────────────

test('markAsNoShow() transitions Registered to NoShow', function () {
    $guest = createGuest();

    $guest->markAsNoShow();

    expect($guest->status())->toBe(GuestStatus::NoShow);
});

test('markAsNoShow() throws from CheckedIn status', function () {
    $guest = createCheckedInGuest();

    $guest->markAsNoShow();
})->throws(DomainException::class);

// ── pullDomainEvents() ─────────────────────────────────────────

test('pullDomainEvents() returns events and clears them', function () {
    $guest = createGuest();
    $guest->checkIn(Uuid::generate());

    $events = $guest->pullDomainEvents();
    expect($events)->toHaveCount(1);

    $eventsAfter = $guest->pullDomainEvents();
    expect($eventsAfter)->toHaveCount(0);
});

// ── INVALID_STATUS_TRANSITION error code ────────────────────────

test('invalid transition throws with INVALID_STATUS_TRANSITION error code', function () {
    $guest = createGuest();
    $guest->deny(Uuid::generate(), 'Motivo');
    $guest->pullDomainEvents();

    try {
        $guest->checkIn(Uuid::generate());
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVALID_STATUS_TRANSITION')
            ->and($e->context())->toHaveKey('current_status', 'denied')
            ->and($e->context())->toHaveKey('target_status', 'checked_in');
    }
});
