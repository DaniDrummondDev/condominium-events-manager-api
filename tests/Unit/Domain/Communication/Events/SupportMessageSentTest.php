<?php

declare(strict_types=1);

use Domain\Communication\Events\SupportMessageSent;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('implements DomainEvent', function () {
    $event = new SupportMessageSent(
        Uuid::generate()->value(),
        Uuid::generate()->value(),
        Uuid::generate()->value(),
        false,
    );

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('has correct event name', function () {
    $event = new SupportMessageSent(
        Uuid::generate()->value(),
        Uuid::generate()->value(),
        Uuid::generate()->value(),
        true,
    );

    expect($event->eventName())->toBe('support_message.sent');
});

test('has correct aggregate id', function () {
    $id = Uuid::generate()->value();
    $event = new SupportMessageSent($id, Uuid::generate()->value(), Uuid::generate()->value(), false);

    expect($event->aggregateId()->value())->toBe($id);
});

test('has correct payload', function () {
    $messageId = Uuid::generate()->value();
    $requestId = Uuid::generate()->value();
    $senderId = Uuid::generate()->value();

    $event = new SupportMessageSent($messageId, $requestId, $senderId, true);

    expect($event->payload())->toBe([
        'message_id' => $messageId,
        'support_request_id' => $requestId,
        'sender_id' => $senderId,
        'is_internal' => true,
    ]);
});
