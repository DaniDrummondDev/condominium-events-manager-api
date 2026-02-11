<?php

declare(strict_types=1);

use Domain\Governance\Events\PenaltyExpired;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('PenaltyExpired implements DomainEvent', function () {
    $event = new PenaltyExpired('pid', 'uid');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('PenaltyExpired has correct eventName', function () {
    $event = new PenaltyExpired('pid', 'uid');

    expect($event->eventName())->toBe('penalty.expired');
});

test('PenaltyExpired aggregateId matches penaltyId', function () {
    $id = Uuid::generate()->value();
    $event = new PenaltyExpired($id, 'uid');

    expect($event->aggregateId()->value())->toBe($id);
});

test('PenaltyExpired occurredAt is set', function () {
    $event = new PenaltyExpired('pid', 'uid');

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('PenaltyExpired payload is correct', function () {
    $event = new PenaltyExpired('p-id', 'u-id');

    expect($event->payload())->toBe([
        'penalty_id' => 'p-id',
        'unit_id' => 'u-id',
    ]);
});
