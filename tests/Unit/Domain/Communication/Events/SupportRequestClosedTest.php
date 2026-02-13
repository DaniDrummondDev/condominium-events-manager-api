<?php

declare(strict_types=1);

use Domain\Communication\Events\SupportRequestClosed;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('implements DomainEvent', function () {
    $event = new SupportRequestClosed(Uuid::generate()->value(), 'resolved');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('has correct event name', function () {
    $event = new SupportRequestClosed(Uuid::generate()->value(), 'admin_closed');

    expect($event->eventName())->toBe('support_request.closed');
});

test('has correct aggregate id', function () {
    $id = Uuid::generate()->value();
    $event = new SupportRequestClosed($id, 'resolved');

    expect($event->aggregateId()->value())->toBe($id);
});

test('has correct payload', function () {
    $id = Uuid::generate()->value();
    $event = new SupportRequestClosed($id, 'admin_closed');

    expect($event->payload())->toBe([
        'support_request_id' => $id,
        'closed_reason' => 'admin_closed',
    ]);
});
