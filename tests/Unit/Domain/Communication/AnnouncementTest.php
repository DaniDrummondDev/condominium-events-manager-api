<?php

declare(strict_types=1);

use Domain\Communication\Entities\Announcement;
use Domain\Communication\Enums\AnnouncementPriority;
use Domain\Communication\Enums\AnnouncementStatus;
use Domain\Communication\Enums\AudienceType;
use Domain\Communication\Events\AnnouncementArchived;
use Domain\Communication\Events\AnnouncementPublished;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

function createAnnouncement(): Announcement
{
    return Announcement::create(
        id: Uuid::generate(),
        title: 'Manutenção programada',
        body: 'A piscina ficará fechada para manutenção.',
        priority: AnnouncementPriority::High,
        audienceType: AudienceType::All,
        audienceIds: null,
        publishedBy: Uuid::generate(),
        expiresAt: null,
    );
}

test('create sets published status and emits event', function () {
    $announcement = createAnnouncement();

    expect($announcement->status())->toBe(AnnouncementStatus::Published);
    expect($announcement->title())->toBe('Manutenção programada');
    expect($announcement->body())->toBe('A piscina ficará fechada para manutenção.');
    expect($announcement->priority())->toBe(AnnouncementPriority::High);
    expect($announcement->audienceType())->toBe(AudienceType::All);
    expect($announcement->audienceIds())->toBeNull();
    expect($announcement->publishedAt())->toBeInstanceOf(DateTimeImmutable::class);
    expect($announcement->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
    expect($announcement->expiresAt())->toBeNull();

    $events = $announcement->pullDomainEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(AnnouncementPublished::class);
});

test('create with audience ids', function () {
    $blockId = Uuid::generate()->value();

    $announcement = Announcement::create(
        id: Uuid::generate(),
        title: 'Aviso do bloco A',
        body: 'Corpo do aviso.',
        priority: AnnouncementPriority::Normal,
        audienceType: AudienceType::Block,
        audienceIds: [$blockId],
        publishedBy: Uuid::generate(),
        expiresAt: new DateTimeImmutable('+7 days'),
    );

    expect($announcement->audienceType())->toBe(AudienceType::Block);
    expect($announcement->audienceIds())->toBe([$blockId]);
    expect($announcement->expiresAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('archive transitions from published to archived', function () {
    $announcement = createAnnouncement();
    $announcement->pullDomainEvents();

    $announcement->archive();

    expect($announcement->status())->toBe(AnnouncementStatus::Archived);

    $events = $announcement->pullDomainEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(AnnouncementArchived::class);
});

test('cannot archive a draft announcement', function () {
    $announcement = new Announcement(
        id: Uuid::generate(),
        title: 'Rascunho',
        body: 'Corpo do rascunho.',
        priority: AnnouncementPriority::Low,
        audienceType: AudienceType::All,
        audienceIds: null,
        status: AnnouncementStatus::Draft,
        publishedBy: Uuid::generate(),
        publishedAt: new DateTimeImmutable,
        expiresAt: null,
        createdAt: new DateTimeImmutable,
    );

    expect(fn () => $announcement->archive())->toThrow(
        DomainException::class,
        "Cannot transition announcement from 'draft' to 'archived'",
    );
});

test('cannot archive an already archived announcement', function () {
    $announcement = createAnnouncement();
    $announcement->pullDomainEvents();
    $announcement->archive();
    $announcement->pullDomainEvents();

    expect(fn () => $announcement->archive())->toThrow(
        DomainException::class,
        "Cannot transition announcement from 'archived' to 'archived'",
    );
});

test('pullDomainEvents clears events', function () {
    $announcement = createAnnouncement();

    $first = $announcement->pullDomainEvents();
    $second = $announcement->pullDomainEvents();

    expect($first)->toHaveCount(1);
    expect($second)->toHaveCount(0);
});
