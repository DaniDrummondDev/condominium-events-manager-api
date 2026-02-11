<?php

declare(strict_types=1);

use Domain\Governance\Events\ViolationRevoked;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('ViolationRevoked implements DomainEvent', function () {
    $event = new ViolationRevoked('vid', 'uid', 'admin-id', 'insufficient evidence');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('ViolationRevoked has correct eventName', function () {
    $event = new ViolationRevoked('vid', 'uid', 'admin-id', 'insufficient evidence');

    expect($event->eventName())->toBe('violation.revoked');
});

test('ViolationRevoked aggregateId matches violationId', function () {
    $id = Uuid::generate()->value();
    $event = new ViolationRevoked($id, 'uid', 'admin-id', 'insufficient evidence');

    expect($event->aggregateId()->value())->toBe($id);
});

test('ViolationRevoked occurredAt is set', function () {
    $event = new ViolationRevoked('vid', 'uid', 'admin-id', 'insufficient evidence');

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('ViolationRevoked payload is correct', function () {
    $event = new ViolationRevoked('v-id', 'u-id', 'admin-id', 'insufficient evidence');

    expect($event->payload())->toBe([
        'violation_id' => 'v-id',
        'unit_id' => 'u-id',
        'revoked_by' => 'admin-id',
        'reason' => 'insufficient evidence',
    ]);
});
