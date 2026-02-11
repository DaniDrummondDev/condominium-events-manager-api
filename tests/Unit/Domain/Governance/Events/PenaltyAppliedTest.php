<?php

declare(strict_types=1);

use Domain\Governance\Events\PenaltyApplied;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('PenaltyApplied implements DomainEvent', function () {
    $event = new PenaltyApplied('pid', 'vid', 'uid', 'suspension', '2025-01-01', '2025-01-31');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('PenaltyApplied has correct eventName', function () {
    $event = new PenaltyApplied('pid', 'vid', 'uid', 'suspension', '2025-01-01', '2025-01-31');

    expect($event->eventName())->toBe('penalty.applied');
});

test('PenaltyApplied aggregateId matches penaltyId', function () {
    $id = Uuid::generate()->value();
    $event = new PenaltyApplied($id, 'vid', 'uid', 'suspension', '2025-01-01', '2025-01-31');

    expect($event->aggregateId()->value())->toBe($id);
});

test('PenaltyApplied occurredAt is set', function () {
    $event = new PenaltyApplied('pid', 'vid', 'uid', 'suspension', '2025-01-01', null);

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('PenaltyApplied payload is correct', function () {
    $event = new PenaltyApplied('p-id', 'v-id', 'u-id', 'suspension', '2025-01-01', '2025-01-31');

    expect($event->payload())->toBe([
        'penalty_id' => 'p-id',
        'violation_id' => 'v-id',
        'unit_id' => 'u-id',
        'type' => 'suspension',
        'starts_at' => '2025-01-01',
        'ends_at' => '2025-01-31',
    ]);
});
