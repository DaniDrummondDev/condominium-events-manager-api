<?php

declare(strict_types=1);

use Domain\Reservation\Events\ReservationCompleted;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('ReservationCompleted implements DomainEvent', function () {
    $event = new ReservationCompleted('id', 'space', 'unit', 'resident');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('ReservationCompleted has correct eventName', function () {
    $event = new ReservationCompleted('id', 'space', 'unit', 'resident');

    expect($event->eventName())->toBe('reservation.completed');
});

test('ReservationCompleted payload contains all fields', function () {
    $event = new ReservationCompleted('r-id', 's-id', 'u-id', 'res-id');

    expect($event->payload())->toBe([
        'reservation_id' => 'r-id',
        'space_id' => 's-id',
        'unit_id' => 'u-id',
        'resident_id' => 'res-id',
    ]);
});

test('ReservationCompleted aggregateId matches reservationId', function () {
    $id = Uuid::generate()->value();
    $event = new ReservationCompleted($id, 'space', 'unit', 'resident');

    expect($event->aggregateId()->value())->toBe($id);
});
