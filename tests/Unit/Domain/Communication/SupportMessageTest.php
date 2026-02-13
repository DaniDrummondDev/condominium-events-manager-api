<?php

declare(strict_types=1);

use Domain\Communication\Entities\SupportMessage;
use Domain\Shared\ValueObjects\Uuid;

test('create sets all properties correctly', function () {
    $id = Uuid::generate();
    $requestId = Uuid::generate();
    $senderId = Uuid::generate();

    $message = SupportMessage::create(
        id: $id,
        supportRequestId: $requestId,
        senderId: $senderId,
        body: 'Mensagem de suporte.',
        isInternal: false,
    );

    expect($message->id())->toBe($id);
    expect($message->supportRequestId())->toBe($requestId);
    expect($message->senderId())->toBe($senderId);
    expect($message->body())->toBe('Mensagem de suporte.');
    expect($message->isInternal())->toBeFalse();
    expect($message->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('create with internal flag', function () {
    $message = SupportMessage::create(
        id: Uuid::generate(),
        supportRequestId: Uuid::generate(),
        senderId: Uuid::generate(),
        body: 'Nota interna para staff.',
        isInternal: true,
    );

    expect($message->isInternal())->toBeTrue();
});
