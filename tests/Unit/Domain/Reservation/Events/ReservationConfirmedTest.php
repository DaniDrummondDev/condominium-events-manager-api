<?php

declare(strict_types=1);

use Domain\Reservation\Events\ReservationConfirmed;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('ReservationConfirmed implements DomainEvent', function () {
    $event = new ReservationConfirmed('id', 'space', 'unit', 'resident');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('ReservationConfirmed has correct eventName', function () {
    $event = new ReservationConfirmed('id', 'space', 'unit', 'resident');

    expect($event->eventName())->toBe('reservation.confirmed');
});

test('ReservationConfirmed payload includes approvedBy when set', function () {
    $event = new ReservationConfirmed('r-id', 's-id', 'u-id', 'res-id', 'admin-id');

    expect($event->payload())->toBe([
        'reservation_id' => 'r-id',
        'space_id' => 's-id',
        'unit_id' => 'u-id',
        'resident_id' => 'res-id',
        'approved_by' => 'admin-id',
    ]);
});

test('ReservationConfirmed approvedBy defaults to null for auto-confirmed', function () {
    $event = new ReservationConfirmed('r-id', 's-id', 'u-id', 'res-id');

    expect($event->approvedBy)->toBeNull()
        ->and($event->payload()['approved_by'])->toBeNull();
});

test('ReservationConfirmed aggregateId matches reservationId', function () {
    $id = Uuid::generate()->value();
    $event = new ReservationConfirmed($id, 'space', 'unit', 'resident');

    expect($event->aggregateId()->value())->toBe($id);
});
