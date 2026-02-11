<?php

declare(strict_types=1);

use Domain\Governance\Events\ViolationUpheld;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('ViolationUpheld implements DomainEvent', function () {
    $event = new ViolationUpheld('vid', 'uid', 'admin-id');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('ViolationUpheld has correct eventName', function () {
    $event = new ViolationUpheld('vid', 'uid', 'admin-id');

    expect($event->eventName())->toBe('violation.upheld');
});

test('ViolationUpheld aggregateId matches violationId', function () {
    $id = Uuid::generate()->value();
    $event = new ViolationUpheld($id, 'uid', 'admin-id');

    expect($event->aggregateId()->value())->toBe($id);
});

test('ViolationUpheld occurredAt is set', function () {
    $event = new ViolationUpheld('vid', 'uid', 'admin-id');

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('ViolationUpheld payload is correct', function () {
    $event = new ViolationUpheld('v-id', 'u-id', 'admin-id');

    expect($event->payload())->toBe([
        'violation_id' => 'v-id',
        'unit_id' => 'u-id',
        'upheld_by' => 'admin-id',
    ]);
});
