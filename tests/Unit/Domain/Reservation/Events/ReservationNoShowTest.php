<?php

declare(strict_types=1);

use Domain\Reservation\Events\ReservationNoShow;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('ReservationNoShow implements DomainEvent', function () {
    $event = new ReservationNoShow('id', 'space', 'unit', 'resident', 'admin');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('ReservationNoShow has correct eventName', function () {
    $event = new ReservationNoShow('id', 'space', 'unit', 'resident', 'admin');

    expect($event->eventName())->toBe('reservation.no_show');
});

test('ReservationNoShow payload contains all fields', function () {
    $event = new ReservationNoShow('r-id', 's-id', 'u-id', 'res-id', 'admin-id');

    expect($event->payload())->toBe([
        'reservation_id' => 'r-id',
        'space_id' => 's-id',
        'unit_id' => 'u-id',
        'resident_id' => 'res-id',
        'no_show_by' => 'admin-id',
    ]);
});

test('ReservationNoShow aggregateId matches reservationId', function () {
    $id = Uuid::generate()->value();
    $event = new ReservationNoShow($id, 'space', 'unit', 'resident', 'admin');

    expect($event->aggregateId()->value())->toBe($id);
});
