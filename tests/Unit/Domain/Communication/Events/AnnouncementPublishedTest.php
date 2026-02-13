<?php

declare(strict_types=1);

use Domain\Communication\Events\AnnouncementPublished;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('implements DomainEvent', function () {
    $event = new AnnouncementPublished(
        Uuid::generate()->value(),
        'Aviso teste',
        'high',
        'all',
        Uuid::generate()->value(),
    );

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('has correct event name', function () {
    $event = new AnnouncementPublished(
        Uuid::generate()->value(),
        'Aviso teste',
        'normal',
        'block',
        Uuid::generate()->value(),
    );

    expect($event->eventName())->toBe('announcement.published');
});

test('has correct aggregate id', function () {
    $id = Uuid::generate()->value();

    $event = new AnnouncementPublished($id, 'Aviso', 'low', 'all', Uuid::generate()->value());

    expect($event->aggregateId()->value())->toBe($id);
});

test('has occurred at timestamp', function () {
    $event = new AnnouncementPublished(
        Uuid::generate()->value(),
        'Aviso',
        'normal',
        'all',
        Uuid::generate()->value(),
    );

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('has correct payload', function () {
    $id = Uuid::generate()->value();
    $publishedBy = Uuid::generate()->value();

    $event = new AnnouncementPublished($id, 'Aviso teste', 'high', 'units', $publishedBy);

    expect($event->payload())->toBe([
        'announcement_id' => $id,
        'title' => 'Aviso teste',
        'priority' => 'high',
        'audience_type' => 'units',
        'published_by' => $publishedBy,
    ]);
});
