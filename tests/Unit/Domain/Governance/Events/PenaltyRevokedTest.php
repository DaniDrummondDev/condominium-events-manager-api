<?php

declare(strict_types=1);

use Domain\Governance\Events\PenaltyRevoked;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('PenaltyRevoked implements DomainEvent', function () {
    $event = new PenaltyRevoked('pid', 'uid', 'admin-id', 'error in judgment');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('PenaltyRevoked has correct eventName', function () {
    $event = new PenaltyRevoked('pid', 'uid', 'admin-id', 'error in judgment');

    expect($event->eventName())->toBe('penalty.revoked');
});

test('PenaltyRevoked aggregateId matches penaltyId', function () {
    $id = Uuid::generate()->value();
    $event = new PenaltyRevoked($id, 'uid', 'admin-id', 'error in judgment');

    expect($event->aggregateId()->value())->toBe($id);
});

test('PenaltyRevoked occurredAt is set', function () {
    $event = new PenaltyRevoked('pid', 'uid', 'admin-id', 'error in judgment');

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('PenaltyRevoked payload is correct', function () {
    $event = new PenaltyRevoked('p-id', 'u-id', 'admin-id', 'error in judgment');

    expect($event->payload())->toBe([
        'penalty_id' => 'p-id',
        'unit_id' => 'u-id',
        'revoked_by' => 'admin-id',
        'reason' => 'error in judgment',
    ]);
});
