<?php

declare(strict_types=1);

use Domain\People\Events\GuestCheckedOut;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('GuestCheckedOut implements DomainEvent', function () {
    $event = new GuestCheckedOut('guest-id', 'reservation-id', 'user-id');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('GuestCheckedOut has correct eventName', function () {
    $event = new GuestCheckedOut('guest-id', 'reservation-id', 'user-id');

    expect($event->eventName())->toBe('guest.checked_out');
});

test('GuestCheckedOut aggregateId matches guestId', function () {
    $id = Uuid::generate()->value();
    $event = new GuestCheckedOut($id, 'reservation-id', 'user-id');

    expect($event->aggregateId()->value())->toBe($id);
});

test('GuestCheckedOut occurredAt is set', function () {
    $event = new GuestCheckedOut('guest-id', 'reservation-id', 'user-id');

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('GuestCheckedOut payload contains all fields', function () {
    $event = new GuestCheckedOut('g-id', 'r-id', 'u-id');

    expect($event->payload())->toBe([
        'guest_id' => 'g-id',
        'reservation_id' => 'r-id',
        'checked_out_by' => 'u-id',
    ]);
});
