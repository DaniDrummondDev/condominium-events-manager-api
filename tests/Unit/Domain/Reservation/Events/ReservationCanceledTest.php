<?php

declare(strict_types=1);

use Domain\Reservation\Events\ReservationCanceled;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('ReservationCanceled implements DomainEvent', function () {
    $event = new ReservationCanceled('id', 'space', 'resident', 'user', 'Motivo');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('ReservationCanceled has correct eventName', function () {
    $event = new ReservationCanceled('id', 'space', 'resident', 'user', 'Motivo');

    expect($event->eventName())->toBe('reservation.canceled');
});

test('ReservationCanceled payload with no late cancellation', function () {
    $event = new ReservationCanceled('r-id', 's-id', 'res-id', 'user-id', 'Imprevisto', false);

    expect($event->payload())->toBe([
        'reservation_id' => 'r-id',
        'space_id' => 's-id',
        'resident_id' => 'res-id',
        'canceled_by' => 'user-id',
        'cancellation_reason' => 'Imprevisto',
        'is_late_cancellation' => false,
    ]);
});

test('ReservationCanceled payload with late cancellation flag', function () {
    $event = new ReservationCanceled('r-id', 's-id', 'res-id', 'user-id', 'Tarde', true);

    expect($event->isLateCancellation)->toBeTrue()
        ->and($event->payload()['is_late_cancellation'])->toBeTrue();
});

test('ReservationCanceled aggregateId matches reservationId', function () {
    $id = Uuid::generate()->value();
    $event = new ReservationCanceled($id, 'space', 'resident', 'user', 'Motivo');

    expect($event->aggregateId()->value())->toBe($id);
});
