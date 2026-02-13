<?php

declare(strict_types=1);

use Domain\People\Events\GuestCheckedIn;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('GuestCheckedIn implements DomainEvent', function () {
    $event = new GuestCheckedIn('guest-id', 'reservation-id', 'user-id');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('GuestCheckedIn has correct eventName', function () {
    $event = new GuestCheckedIn('guest-id', 'reservation-id', 'user-id');

    expect($event->eventName())->toBe('guest.checked_in');
});

test('GuestCheckedIn aggregateId matches guestId', function () {
    $id = Uuid::generate()->value();
    $event = new GuestCheckedIn($id, 'reservation-id', 'user-id');

    expect($event->aggregateId()->value())->toBe($id);
});

test('GuestCheckedIn occurredAt is set', function () {
    $event = new GuestCheckedIn('guest-id', 'reservation-id', 'user-id');

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('GuestCheckedIn payload contains all fields', function () {
    $event = new GuestCheckedIn('g-id', 'r-id', 'u-id');

    expect($event->payload())->toBe([
        'guest_id' => 'g-id',
        'reservation_id' => 'r-id',
        'checked_in_by' => 'u-id',
    ]);
});
