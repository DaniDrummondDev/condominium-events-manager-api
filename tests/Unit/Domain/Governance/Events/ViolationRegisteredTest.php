<?php

declare(strict_types=1);

use Domain\Governance\Events\ViolationRegistered;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('ViolationRegistered implements DomainEvent', function () {
    $event = new ViolationRegistered('vid', 'uid', 'noise', 'high', true);

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('ViolationRegistered has correct eventName', function () {
    $event = new ViolationRegistered('vid', 'uid', 'noise', 'high', false);

    expect($event->eventName())->toBe('violation.registered');
});

test('ViolationRegistered aggregateId matches violationId', function () {
    $id = Uuid::generate()->value();
    $event = new ViolationRegistered($id, 'uid', 'noise', 'high', true);

    expect($event->aggregateId()->value())->toBe($id);
});

test('ViolationRegistered occurredAt is set', function () {
    $event = new ViolationRegistered('vid', 'uid', 'noise', 'high', false);

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('ViolationRegistered payload is correct', function () {
    $event = new ViolationRegistered('v-id', 'u-id', 'noise', 'high', true);

    expect($event->payload())->toBe([
        'violation_id' => 'v-id',
        'unit_id' => 'u-id',
        'type' => 'noise',
        'severity' => 'high',
        'is_automatic' => true,
    ]);
});
