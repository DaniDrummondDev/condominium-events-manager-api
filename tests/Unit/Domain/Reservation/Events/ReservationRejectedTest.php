<?php

declare(strict_types=1);

use Domain\Reservation\Events\ReservationRejected;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('ReservationRejected implements DomainEvent', function () {
    $event = new ReservationRejected('id', 'space', 'resident', 'admin', 'Motivo');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('ReservationRejected has correct eventName', function () {
    $event = new ReservationRejected('id', 'space', 'resident', 'admin', 'Motivo');

    expect($event->eventName())->toBe('reservation.rejected');
});

test('ReservationRejected payload contains all fields', function () {
    $event = new ReservationRejected('r-id', 's-id', 'res-id', 'admin-id', 'Sem vagas');

    expect($event->payload())->toBe([
        'reservation_id' => 'r-id',
        'space_id' => 's-id',
        'resident_id' => 'res-id',
        'rejected_by' => 'admin-id',
        'rejection_reason' => 'Sem vagas',
    ]);
});

test('ReservationRejected aggregateId matches reservationId', function () {
    $id = Uuid::generate()->value();
    $event = new ReservationRejected($id, 'space', 'resident', 'admin', 'Motivo');

    expect($event->aggregateId()->value())->toBe($id);
});
