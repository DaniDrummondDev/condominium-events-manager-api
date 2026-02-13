<?php

declare(strict_types=1);

use Domain\Communication\Events\SupportRequestUpdated;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('implements DomainEvent', function () {
    $event = new SupportRequestUpdated(Uuid::generate()->value(), 'in_progress');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('has correct event name', function () {
    $event = new SupportRequestUpdated(Uuid::generate()->value(), 'in_progress');

    expect($event->eventName())->toBe('support_request.updated');
});

test('has correct aggregate id', function () {
    $id = Uuid::generate()->value();
    $event = new SupportRequestUpdated($id, 'open');

    expect($event->aggregateId()->value())->toBe($id);
});

test('has correct payload', function () {
    $id = Uuid::generate()->value();
    $event = new SupportRequestUpdated($id, 'in_progress');

    expect($event->payload())->toBe([
        'support_request_id' => $id,
        'new_status' => 'in_progress',
    ]);
});
