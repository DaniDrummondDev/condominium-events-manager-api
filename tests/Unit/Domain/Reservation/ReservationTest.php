<?php

declare(strict_types=1);

use Domain\Reservation\Entities\Reservation;
use Domain\Reservation\Enums\ReservationStatus;
use Domain\Reservation\Events\ReservationCanceled;
use Domain\Reservation\Events\ReservationCompleted;
use Domain\Reservation\Events\ReservationConfirmed;
use Domain\Reservation\Events\ReservationNoShow;
use Domain\Reservation\Events\ReservationRejected;
use Domain\Reservation\Events\ReservationRequested;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\DateRange;
use Domain\Shared\ValueObjects\Uuid;

function createReservation(
    bool $requiresApproval = true,
    ?Uuid $id = null,
    ?Uuid $spaceId = null,
    ?Uuid $unitId = null,
    ?Uuid $residentId = null,
    ?string $title = 'Churrasco de Aniversário',
    ?DateTimeImmutable $startDatetime = null,
    ?DateTimeImmutable $endDatetime = null,
    int $expectedGuests = 20,
    ?string $notes = null,
): Reservation {
    return Reservation::create(
        $id ?? Uuid::generate(),
        $spaceId ?? Uuid::generate(),
        $unitId ?? Uuid::generate(),
        $residentId ?? Uuid::generate(),
        $title,
        $startDatetime ?? new DateTimeImmutable('+1 day 10:00'),
        $endDatetime ?? new DateTimeImmutable('+1 day 14:00'),
        $expectedGuests,
        $notes,
        $requiresApproval,
    );
}

function createConfirmedReservation(): Reservation
{
    $reservation = createReservation(requiresApproval: true);
    $reservation->approve(Uuid::generate());
    $reservation->pullDomainEvents(); // clear events

    return $reservation;
}

function createInProgressReservation(): Reservation
{
    $reservation = createConfirmedReservation();
    $reservation->checkIn();
    $reservation->pullDomainEvents();

    return $reservation;
}

// ── Factory: create() with requiresApproval=true ──────────────

test('create() with approval sets PendingApproval status', function () {
    $reservation = createReservation(requiresApproval: true);

    expect($reservation->status())->toBe(ReservationStatus::PendingApproval);
});

test('create() with approval emits ReservationRequested event', function () {
    $reservation = createReservation(requiresApproval: true);
    $events = $reservation->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ReservationRequested::class);
});

test('create() with approval event contains correct payload', function () {
    $id = Uuid::generate();
    $spaceId = Uuid::generate();
    $unitId = Uuid::generate();
    $residentId = Uuid::generate();
    $start = new DateTimeImmutable('+2 days 10:00');
    $end = new DateTimeImmutable('+2 days 14:00');

    $reservation = createReservation(
        requiresApproval: true,
        id: $id,
        spaceId: $spaceId,
        unitId: $unitId,
        residentId: $residentId,
        startDatetime: $start,
        endDatetime: $end,
    );
    $events = $reservation->pullDomainEvents();

    expect($events[0]->reservationId)->toBe($id->value())
        ->and($events[0]->spaceId)->toBe($spaceId->value())
        ->and($events[0]->unitId)->toBe($unitId->value())
        ->and($events[0]->residentId)->toBe($residentId->value())
        ->and($events[0]->startDatetime)->toBe($start->format('c'))
        ->and($events[0]->endDatetime)->toBe($end->format('c'));
});

// ── Factory: create() with requiresApproval=false ──────────────

test('create() without approval sets Confirmed status', function () {
    $reservation = createReservation(requiresApproval: false);

    expect($reservation->status())->toBe(ReservationStatus::Confirmed);
});

test('create() without approval emits ReservationConfirmed event', function () {
    $reservation = createReservation(requiresApproval: false);
    $events = $reservation->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ReservationConfirmed::class)
        ->and($events[0]->approvedBy)->toBeNull();
});

// ── Factory: properties ──────────────────────────────────────

test('create() sets all properties correctly', function () {
    $id = Uuid::generate();
    $spaceId = Uuid::generate();
    $unitId = Uuid::generate();
    $residentId = Uuid::generate();
    $start = new DateTimeImmutable('+1 day 09:00');
    $end = new DateTimeImmutable('+1 day 13:00');

    $reservation = createReservation(
        requiresApproval: true,
        id: $id,
        spaceId: $spaceId,
        unitId: $unitId,
        residentId: $residentId,
        title: 'Festa',
        startDatetime: $start,
        endDatetime: $end,
        expectedGuests: 15,
        notes: 'Trazer som',
    );

    expect($reservation->id())->toBe($id)
        ->and($reservation->spaceId())->toBe($spaceId)
        ->and($reservation->unitId())->toBe($unitId)
        ->and($reservation->residentId())->toBe($residentId)
        ->and($reservation->title())->toBe('Festa')
        ->and($reservation->startDatetime())->toBe($start)
        ->and($reservation->endDatetime())->toBe($end)
        ->and($reservation->expectedGuests())->toBe(15)
        ->and($reservation->notes())->toBe('Trazer som')
        ->and($reservation->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('create() initializes nullable fields as null', function () {
    $reservation = createReservation();

    expect($reservation->approvedBy())->toBeNull()
        ->and($reservation->approvedAt())->toBeNull()
        ->and($reservation->rejectedBy())->toBeNull()
        ->and($reservation->rejectedAt())->toBeNull()
        ->and($reservation->rejectionReason())->toBeNull()
        ->and($reservation->canceledBy())->toBeNull()
        ->and($reservation->canceledAt())->toBeNull()
        ->and($reservation->cancellationReason())->toBeNull()
        ->and($reservation->completedAt())->toBeNull()
        ->and($reservation->noShowAt())->toBeNull()
        ->and($reservation->noShowBy())->toBeNull()
        ->and($reservation->checkedInAt())->toBeNull();
});

test('create() with null title and notes', function () {
    $reservation = createReservation(title: null, notes: null);

    expect($reservation->title())->toBeNull()
        ->and($reservation->notes())->toBeNull();
});

// ── approve() ────────────────────────────────────────────────

test('approve() transitions PendingApproval to Confirmed', function () {
    $reservation = createReservation(requiresApproval: true);
    $approvedBy = Uuid::generate();

    $reservation->approve($approvedBy);

    expect($reservation->status())->toBe(ReservationStatus::Confirmed)
        ->and($reservation->approvedBy())->toBe($approvedBy)
        ->and($reservation->approvedAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('approve() emits ReservationConfirmed event with approvedBy', function () {
    $reservation = createReservation(requiresApproval: true);
    $reservation->pullDomainEvents(); // clear ReservationRequested
    $approvedBy = Uuid::generate();

    $reservation->approve($approvedBy);
    $events = $reservation->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ReservationConfirmed::class)
        ->and($events[0]->approvedBy)->toBe($approvedBy->value());
});

test('approve() throws from Confirmed status', function () {
    $reservation = createConfirmedReservation();

    $reservation->approve(Uuid::generate());
})->throws(DomainException::class, "Cannot transition reservation from 'confirmed' to 'confirmed'");

test('approve() throws from terminal status', function () {
    $reservation = createReservation(requiresApproval: true);
    $reservation->reject(Uuid::generate(), 'Sem motivo');

    $reservation->approve(Uuid::generate());
})->throws(DomainException::class);

// ── reject() ─────────────────────────────────────────────────

test('reject() transitions PendingApproval to Rejected', function () {
    $reservation = createReservation(requiresApproval: true);
    $rejectedBy = Uuid::generate();

    $reservation->reject($rejectedBy, 'Horário indisponível');

    expect($reservation->status())->toBe(ReservationStatus::Rejected)
        ->and($reservation->rejectedBy())->toBe($rejectedBy)
        ->and($reservation->rejectedAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($reservation->rejectionReason())->toBe('Horário indisponível');
});

test('reject() emits ReservationRejected event', function () {
    $reservation = createReservation(requiresApproval: true);
    $reservation->pullDomainEvents();
    $rejectedBy = Uuid::generate();

    $reservation->reject($rejectedBy, 'Motivo');
    $events = $reservation->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ReservationRejected::class)
        ->and($events[0]->rejectedBy)->toBe($rejectedBy->value())
        ->and($events[0]->rejectionReason)->toBe('Motivo');
});

test('reject() throws from Confirmed status', function () {
    $reservation = createConfirmedReservation();

    $reservation->reject(Uuid::generate(), 'Motivo');
})->throws(DomainException::class);

// ── cancel() ─────────────────────────────────────────────────

test('cancel() transitions PendingApproval to Canceled', function () {
    $reservation = createReservation(requiresApproval: true);
    $canceledBy = Uuid::generate();

    $reservation->cancel($canceledBy, 'Mudei de planos');

    expect($reservation->status())->toBe(ReservationStatus::Canceled)
        ->and($reservation->canceledBy())->toBe($canceledBy)
        ->and($reservation->canceledAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($reservation->cancellationReason())->toBe('Mudei de planos');
});

test('cancel() transitions Confirmed to Canceled', function () {
    $reservation = createConfirmedReservation();
    $canceledBy = Uuid::generate();

    $reservation->cancel($canceledBy, 'Imprevisto');

    expect($reservation->status())->toBe(ReservationStatus::Canceled);
});

test('cancel() emits ReservationCanceled event', function () {
    $reservation = createReservation(requiresApproval: true);
    $reservation->pullDomainEvents();
    $canceledBy = Uuid::generate();

    $reservation->cancel($canceledBy, 'Motivo', isLateCancellation: false);
    $events = $reservation->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ReservationCanceled::class)
        ->and($events[0]->canceledBy)->toBe($canceledBy->value())
        ->and($events[0]->cancellationReason)->toBe('Motivo')
        ->and($events[0]->isLateCancellation)->toBeFalse();
});

test('cancel() with late cancellation flag', function () {
    $reservation = createConfirmedReservation();
    $reservation->pullDomainEvents();

    $reservation->cancel(Uuid::generate(), 'Tarde demais', isLateCancellation: true);
    $events = $reservation->pullDomainEvents();

    expect($events[0]->isLateCancellation)->toBeTrue();
});

test('cancel() throws from InProgress status', function () {
    $reservation = createInProgressReservation();

    $reservation->cancel(Uuid::generate(), 'Motivo');
})->throws(DomainException::class);

test('cancel() throws from terminal status', function () {
    $reservation = createReservation();
    $reservation->reject(Uuid::generate(), 'Motivo');

    $reservation->cancel(Uuid::generate(), 'Motivo');
})->throws(DomainException::class);

// ── checkIn() ────────────────────────────────────────────────

test('checkIn() transitions Confirmed to InProgress', function () {
    $reservation = createConfirmedReservation();

    $reservation->checkIn();

    expect($reservation->status())->toBe(ReservationStatus::InProgress)
        ->and($reservation->checkedInAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('checkIn() throws from PendingApproval status', function () {
    $reservation = createReservation(requiresApproval: true);

    $reservation->checkIn();
})->throws(DomainException::class);

test('checkIn() throws from terminal status', function () {
    $reservation = createInProgressReservation();
    $reservation->complete();

    $reservation->checkIn();
})->throws(DomainException::class);

// ── complete() ───────────────────────────────────────────────

test('complete() transitions InProgress to Completed', function () {
    $reservation = createInProgressReservation();

    $reservation->complete();

    expect($reservation->status())->toBe(ReservationStatus::Completed)
        ->and($reservation->completedAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('complete() emits ReservationCompleted event', function () {
    $reservation = createInProgressReservation();

    $reservation->complete();
    $events = $reservation->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ReservationCompleted::class);
});

test('complete() throws from Confirmed status', function () {
    $reservation = createConfirmedReservation();

    $reservation->complete();
})->throws(DomainException::class);

// ── markAsNoShow() ───────────────────────────────────────────

test('markAsNoShow() transitions InProgress to NoShow', function () {
    $reservation = createInProgressReservation();
    $noShowBy = Uuid::generate();

    $reservation->markAsNoShow($noShowBy);

    expect($reservation->status())->toBe(ReservationStatus::NoShow)
        ->and($reservation->noShowAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($reservation->noShowBy())->toBe($noShowBy);
});

test('markAsNoShow() emits ReservationNoShow event', function () {
    $reservation = createInProgressReservation();
    $noShowBy = Uuid::generate();

    $reservation->markAsNoShow($noShowBy);
    $events = $reservation->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ReservationNoShow::class)
        ->and($events[0]->noShowBy)->toBe($noShowBy->value());
});

test('markAsNoShow() throws from PendingApproval status', function () {
    $reservation = createReservation(requiresApproval: true);

    $reservation->markAsNoShow(Uuid::generate());
})->throws(DomainException::class);

test('markAsNoShow() throws from Confirmed status', function () {
    $reservation = createConfirmedReservation();

    $reservation->markAsNoShow(Uuid::generate());
})->throws(DomainException::class);

// ── period() ─────────────────────────────────────────────────

test('period() returns DateRange with start and end', function () {
    $start = new DateTimeImmutable('+1 day 10:00');
    $end = new DateTimeImmutable('+1 day 14:00');
    $reservation = createReservation(startDatetime: $start, endDatetime: $end);

    $period = $reservation->period();

    expect($period)->toBeInstanceOf(DateRange::class)
        ->and($period->start())->toBe($start)
        ->and($period->end())->toBe($end);
});

// ── isLateCancellation() ─────────────────────────────────────

test('isLateCancellation() returns true when within deadline', function () {
    // Reservation starts in 2 hours, deadline is 24 hours
    $reservation = createReservation(
        startDatetime: new DateTimeImmutable('+2 hours'),
        endDatetime: new DateTimeImmutable('+6 hours'),
    );

    expect($reservation->isLateCancellation(24))->toBeTrue();
});

test('isLateCancellation() returns false when outside deadline', function () {
    // Reservation starts in 48 hours, deadline is 24 hours
    $reservation = createReservation(
        startDatetime: new DateTimeImmutable('+48 hours'),
        endDatetime: new DateTimeImmutable('+52 hours'),
    );

    expect($reservation->isLateCancellation(24))->toBeFalse();
});

// ── pullDomainEvents() ───────────────────────────────────────

test('pullDomainEvents() returns events and clears them', function () {
    $reservation = createReservation(requiresApproval: true);

    $events = $reservation->pullDomainEvents();
    expect($events)->toHaveCount(1);

    $eventsAfter = $reservation->pullDomainEvents();
    expect($eventsAfter)->toHaveCount(0);
});

// ── INVALID_STATUS_TRANSITION error code ─────────────────────

test('invalid transition throws with INVALID_STATUS_TRANSITION error code', function () {
    $reservation = createReservation(requiresApproval: true);

    try {
        $reservation->checkIn();
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVALID_STATUS_TRANSITION')
            ->and($e->context())->toHaveKey('current_status', 'pending_approval')
            ->and($e->context())->toHaveKey('target_status', 'in_progress');
    }
});
