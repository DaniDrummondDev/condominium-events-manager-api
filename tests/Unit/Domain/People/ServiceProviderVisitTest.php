<?php

declare(strict_types=1);

use Domain\People\Entities\ServiceProviderVisit;
use Domain\People\Enums\ServiceProviderVisitStatus;
use Domain\People\Events\ServiceProviderCheckedIn;
use Domain\People\Events\ServiceProviderCheckedOut;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

function createVisit(
    ?Uuid $id = null,
    ?Uuid $serviceProviderId = null,
    ?Uuid $unitId = null,
    ?Uuid $reservationId = null,
    ?DateTimeImmutable $scheduledDate = null,
    string $purpose = 'Manutenção do elevador',
    ?string $notes = null,
): ServiceProviderVisit {
    return ServiceProviderVisit::create(
        $id ?? Uuid::generate(),
        $serviceProviderId ?? Uuid::generate(),
        $unitId ?? Uuid::generate(),
        $reservationId,
        $scheduledDate ?? new DateTimeImmutable('+1 day'),
        $purpose,
        $notes,
    );
}

function createCheckedInVisit(): ServiceProviderVisit
{
    $visit = createVisit();
    $visit->checkIn(Uuid::generate());
    $visit->pullDomainEvents();

    return $visit;
}

// ── Factory: create() ──────────────────────────────────────────

test('create() sets Scheduled status', function () {
    $visit = createVisit();

    expect($visit->status())->toBe(ServiceProviderVisitStatus::Scheduled);
});

test('create() sets all properties correctly', function () {
    $id = Uuid::generate();
    $providerId = Uuid::generate();
    $unitId = Uuid::generate();
    $reservationId = Uuid::generate();
    $date = new DateTimeImmutable('+2 days');

    $visit = createVisit(
        id: $id,
        serviceProviderId: $providerId,
        unitId: $unitId,
        reservationId: $reservationId,
        scheduledDate: $date,
        purpose: 'Decoração',
        notes: 'Chegar às 8h',
    );

    expect($visit->id())->toBe($id)
        ->and($visit->serviceProviderId())->toBe($providerId)
        ->and($visit->unitId())->toBe($unitId)
        ->and($visit->reservationId())->toBe($reservationId)
        ->and($visit->scheduledDate())->toBe($date)
        ->and($visit->purpose())->toBe('Decoração')
        ->and($visit->notes())->toBe('Chegar às 8h')
        ->and($visit->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('create() initializes nullable fields as null', function () {
    $visit = createVisit();

    expect($visit->checkedInAt())->toBeNull()
        ->and($visit->checkedOutAt())->toBeNull()
        ->and($visit->checkedInBy())->toBeNull()
        ->and($visit->reservationId())->toBeNull();
});

// ── checkIn() ──────────────────────────────────────────────────

test('checkIn() transitions Scheduled to CheckedIn', function () {
    $visit = createVisit();
    $checkedInBy = Uuid::generate();

    $visit->checkIn($checkedInBy);

    expect($visit->status())->toBe(ServiceProviderVisitStatus::CheckedIn)
        ->and($visit->checkedInAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($visit->checkedInBy())->toBe($checkedInBy);
});

test('checkIn() emits ServiceProviderCheckedIn event', function () {
    $visit = createVisit();
    $checkedInBy = Uuid::generate();

    $visit->checkIn($checkedInBy);
    $events = $visit->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ServiceProviderCheckedIn::class)
        ->and($events[0]->visitId)->toBe($visit->id()->value())
        ->and($events[0]->serviceProviderId)->toBe($visit->serviceProviderId()->value())
        ->and($events[0]->unitId)->toBe($visit->unitId()->value())
        ->and($events[0]->checkedInBy)->toBe($checkedInBy->value());
});

test('checkIn() throws from CheckedOut status', function () {
    $visit = createCheckedInVisit();
    $visit->checkOut(Uuid::generate());

    $visit->checkIn(Uuid::generate());
})->throws(DomainException::class, "Cannot transition visit from 'checked_out' to 'checked_in'");

test('checkIn() throws from Canceled status', function () {
    $visit = createVisit();
    $visit->cancel();

    $visit->checkIn(Uuid::generate());
})->throws(DomainException::class);

// ── checkOut() ─────────────────────────────────────────────────

test('checkOut() transitions CheckedIn to CheckedOut', function () {
    $visit = createCheckedInVisit();

    $visit->checkOut(Uuid::generate());

    expect($visit->status())->toBe(ServiceProviderVisitStatus::CheckedOut)
        ->and($visit->checkedOutAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('checkOut() emits ServiceProviderCheckedOut event', function () {
    $visit = createCheckedInVisit();
    $checkedOutBy = Uuid::generate();

    $visit->checkOut($checkedOutBy);
    $events = $visit->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ServiceProviderCheckedOut::class)
        ->and($events[0]->checkedOutBy)->toBe($checkedOutBy->value());
});

test('checkOut() throws from Scheduled status', function () {
    $visit = createVisit();

    $visit->checkOut(Uuid::generate());
})->throws(DomainException::class);

// ── cancel() ───────────────────────────────────────────────────

test('cancel() transitions Scheduled to Canceled', function () {
    $visit = createVisit();

    $visit->cancel();

    expect($visit->status())->toBe(ServiceProviderVisitStatus::Canceled);
});

test('cancel() throws from CheckedIn status', function () {
    $visit = createCheckedInVisit();

    $visit->cancel();
})->throws(DomainException::class);

// ── markAsNoShow() ─────────────────────────────────────────────

test('markAsNoShow() transitions Scheduled to NoShow', function () {
    $visit = createVisit();

    $visit->markAsNoShow();

    expect($visit->status())->toBe(ServiceProviderVisitStatus::NoShow);
});

test('markAsNoShow() throws from CheckedIn status', function () {
    $visit = createCheckedInVisit();

    $visit->markAsNoShow();
})->throws(DomainException::class);

// ── pullDomainEvents() ─────────────────────────────────────────

test('pullDomainEvents() returns events and clears them', function () {
    $visit = createVisit();
    $visit->checkIn(Uuid::generate());

    $events = $visit->pullDomainEvents();
    expect($events)->toHaveCount(1);

    $eventsAfter = $visit->pullDomainEvents();
    expect($eventsAfter)->toHaveCount(0);
});

// ── INVALID_STATUS_TRANSITION error code ────────────────────────

test('invalid transition throws with INVALID_STATUS_TRANSITION error code', function () {
    $visit = createVisit();
    $visit->cancel();

    try {
        $visit->checkIn(Uuid::generate());
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVALID_STATUS_TRANSITION')
            ->and($e->context())->toHaveKey('current_status', 'canceled')
            ->and($e->context())->toHaveKey('target_status', 'checked_in');
    }
});
