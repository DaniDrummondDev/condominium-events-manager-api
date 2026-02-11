<?php

declare(strict_types=1);

use Domain\Reservation\Events\ReservationRequested;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('ReservationRequested implements DomainEvent', function () {
    $event = new ReservationRequested('id', 'space', 'unit', 'resident', '2026-03-01T10:00:00+00:00', '2026-03-01T14:00:00+00:00');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('ReservationRequested has correct eventName', function () {
    $event = new ReservationRequested('id', 'space', 'unit', 'resident', '2026-03-01T10:00:00+00:00', '2026-03-01T14:00:00+00:00');

    expect($event->eventName())->toBe('reservation.requested');
});

test('ReservationRequested aggregateId matches reservationId', function () {
    $id = Uuid::generate()->value();
    $event = new ReservationRequested($id, 'space', 'unit', 'resident', '2026-03-01T10:00:00+00:00', '2026-03-01T14:00:00+00:00');

    expect($event->aggregateId()->value())->toBe($id);
});

test('ReservationRequested occurredAt is set', function () {
    $event = new ReservationRequested('id', 'space', 'unit', 'resident', '2026-03-01T10:00:00+00:00', '2026-03-01T14:00:00+00:00');

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('ReservationRequested payload contains all fields', function () {
    $event = new ReservationRequested('r-id', 's-id', 'u-id', 'res-id', '2026-03-01T10:00:00+00:00', '2026-03-01T14:00:00+00:00');

    expect($event->payload())->toBe([
        'reservation_id' => 'r-id',
        'space_id' => 's-id',
        'unit_id' => 'u-id',
        'resident_id' => 'res-id',
        'start_datetime' => '2026-03-01T10:00:00+00:00',
        'end_datetime' => '2026-03-01T14:00:00+00:00',
    ]);
});
