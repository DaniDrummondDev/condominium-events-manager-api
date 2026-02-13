<?php

declare(strict_types=1);

use Domain\Communication\Events\AnnouncementArchived;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('implements DomainEvent', function () {
    $event = new AnnouncementArchived(Uuid::generate()->value(), 'Aviso');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('has correct event name', function () {
    $event = new AnnouncementArchived(Uuid::generate()->value(), 'Aviso');

    expect($event->eventName())->toBe('announcement.archived');
});

test('has correct aggregate id', function () {
    $id = Uuid::generate()->value();
    $event = new AnnouncementArchived($id, 'Aviso');

    expect($event->aggregateId()->value())->toBe($id);
});

test('has occurred at timestamp', function () {
    $event = new AnnouncementArchived(Uuid::generate()->value(), 'Aviso');

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('has correct payload', function () {
    $id = Uuid::generate()->value();
    $event = new AnnouncementArchived($id, 'Aviso teste');

    expect($event->payload())->toBe([
        'announcement_id' => $id,
        'title' => 'Aviso teste',
    ]);
});
