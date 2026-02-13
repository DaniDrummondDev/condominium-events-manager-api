<?php

declare(strict_types=1);

use Domain\People\Events\GuestAccessDenied;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('GuestAccessDenied implements DomainEvent', function () {
    $event = new GuestAccessDenied('guest-id', 'reservation-id', 'user-id', 'Documento inválido');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('GuestAccessDenied has correct eventName', function () {
    $event = new GuestAccessDenied('guest-id', 'reservation-id', 'user-id', 'Motivo');

    expect($event->eventName())->toBe('guest.access_denied');
});

test('GuestAccessDenied aggregateId matches guestId', function () {
    $id = Uuid::generate()->value();
    $event = new GuestAccessDenied($id, 'reservation-id', 'user-id', 'Motivo');

    expect($event->aggregateId()->value())->toBe($id);
});

test('GuestAccessDenied occurredAt is set', function () {
    $event = new GuestAccessDenied('guest-id', 'reservation-id', 'user-id', 'Motivo');

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('GuestAccessDenied payload contains all fields', function () {
    $event = new GuestAccessDenied('g-id', 'r-id', 'u-id', 'Sem autorização');

    expect($event->payload())->toBe([
        'guest_id' => 'g-id',
        'reservation_id' => 'r-id',
        'denied_by' => 'u-id',
        'reason' => 'Sem autorização',
    ]);
});
