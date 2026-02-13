<?php

declare(strict_types=1);

use Domain\Communication\Events\SupportRequestCreated;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('implements DomainEvent', function () {
    $event = new SupportRequestCreated(
        Uuid::generate()->value(),
        Uuid::generate()->value(),
        'Assunto',
        'maintenance',
        'high',
    );

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('has correct event name', function () {
    $event = new SupportRequestCreated(
        Uuid::generate()->value(),
        Uuid::generate()->value(),
        'Assunto',
        'maintenance',
        'normal',
    );

    expect($event->eventName())->toBe('support_request.created');
});

test('has correct aggregate id', function () {
    $id = Uuid::generate()->value();

    $event = new SupportRequestCreated($id, Uuid::generate()->value(), 'A', 'general', 'low');

    expect($event->aggregateId()->value())->toBe($id);
});

test('has correct payload', function () {
    $id = Uuid::generate()->value();
    $userId = Uuid::generate()->value();

    $event = new SupportRequestCreated($id, $userId, 'Torneira', 'maintenance', 'high');

    expect($event->payload())->toBe([
        'support_request_id' => $id,
        'user_id' => $userId,
        'subject' => 'Torneira',
        'category' => 'maintenance',
        'priority' => 'high',
    ]);
});
