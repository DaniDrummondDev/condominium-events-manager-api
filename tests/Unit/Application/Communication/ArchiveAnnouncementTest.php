<?php

declare(strict_types=1);

use Application\Communication\Contracts\AnnouncementRepositoryInterface;
use Application\Communication\DTOs\AnnouncementDTO;
use Application\Communication\UseCases\ArchiveAnnouncement;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Communication\Entities\Announcement;
use Domain\Communication\Enums\AnnouncementPriority;
use Domain\Communication\Enums\AudienceType;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createPublishedAnnouncementForArchive(): Announcement
{
    $announcement = Announcement::create(
        id: Uuid::generate(),
        title: 'Aviso',
        body: 'Corpo do aviso.',
        priority: AnnouncementPriority::Normal,
        audienceType: AudienceType::All,
        audienceIds: null,
        publishedBy: Uuid::generate(),
        expiresAt: null,
    );
    $announcement->pullDomainEvents();

    return $announcement;
}

test('archives a published announcement', function () {
    $announcement = createPublishedAnnouncementForArchive();

    $repo = Mockery::mock(AnnouncementRepositoryInterface::class);
    $repo->shouldReceive('findById')->once()->andReturn($announcement);
    $repo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new ArchiveAnnouncement($repo, $eventDispatcher);
    $result = $useCase->execute($announcement->id()->value());

    expect($result)->toBeInstanceOf(AnnouncementDTO::class)
        ->and($result->status)->toBe('archived');
});

test('throws when announcement not found', function () {
    $repo = Mockery::mock(AnnouncementRepositoryInterface::class);
    $repo->shouldReceive('findById')->once()->andReturnNull();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ArchiveAnnouncement($repo, $eventDispatcher);
    $useCase->execute(Uuid::generate()->value());
})->throws(DomainException::class, 'Announcement not found');
