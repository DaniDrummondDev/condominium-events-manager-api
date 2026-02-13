<?php

declare(strict_types=1);

use Domain\Communication\Events\SupportRequestResolved;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('implements DomainEvent', function () {
    $event = new SupportRequestResolved(Uuid::generate()->value(), Uuid::generate()->value());

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('has correct event name', function () {
    $event = new SupportRequestResolved(Uuid::generate()->value(), Uuid::generate()->value());

    expect($event->eventName())->toBe('support_request.resolved');
});

test('has correct aggregate id', function () {
    $id = Uuid::generate()->value();
    $event = new SupportRequestResolved($id, Uuid::generate()->value());

    expect($event->aggregateId()->value())->toBe($id);
});

test('has correct payload', function () {
    $id = Uuid::generate()->value();
    $userId = Uuid::generate()->value();
    $event = new SupportRequestResolved($id, $userId);

    expect($event->payload())->toBe([
        'support_request_id' => $id,
        'user_id' => $userId,
    ]);
});
