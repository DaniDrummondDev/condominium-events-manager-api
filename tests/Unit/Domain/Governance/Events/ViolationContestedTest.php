<?php

declare(strict_types=1);

use Domain\Governance\Events\ViolationContested;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('ViolationContested implements DomainEvent', function () {
    $event = new ViolationContested('vid', 'uid');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('ViolationContested has correct eventName', function () {
    $event = new ViolationContested('vid', 'uid');

    expect($event->eventName())->toBe('violation.contested');
});

test('ViolationContested aggregateId matches violationId', function () {
    $id = Uuid::generate()->value();
    $event = new ViolationContested($id, 'uid');

    expect($event->aggregateId()->value())->toBe($id);
});

test('ViolationContested occurredAt is set', function () {
    $event = new ViolationContested('vid', 'uid');

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('ViolationContested payload is correct', function () {
    $event = new ViolationContested('v-id', 'u-id');

    expect($event->payload())->toBe([
        'violation_id' => 'v-id',
        'unit_id' => 'u-id',
    ]);
});
