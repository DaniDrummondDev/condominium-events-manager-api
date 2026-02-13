<?php

declare(strict_types=1);

use Application\Communication\Contracts\AnnouncementReadRepositoryInterface;
use Application\Communication\Contracts\AnnouncementRepositoryInterface;
use Application\Communication\UseCases\MarkAnnouncementAsRead;
use Domain\Communication\Entities\Announcement;
use Domain\Communication\Enums\AnnouncementPriority;
use Domain\Communication\Enums\AudienceType;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createPublishedAnnouncementForRead(): Announcement
{
    $announcement = Announcement::create(
        id: Uuid::generate(),
        title: 'Aviso',
        body: 'Corpo.',
        priority: AnnouncementPriority::Normal,
        audienceType: AudienceType::All,
        audienceIds: null,
        publishedBy: Uuid::generate(),
        expiresAt: null,
    );
    $announcement->pullDomainEvents();

    return $announcement;
}

test('marks announcement as read', function () {
    $announcement = createPublishedAnnouncementForRead();
    $userId = Uuid::generate()->value();

    $announcementRepo = Mockery::mock(AnnouncementRepositoryInterface::class);
    $announcementRepo->shouldReceive('findById')->once()->andReturn($announcement);

    $readRepo = Mockery::mock(AnnouncementReadRepositoryInterface::class);
    $readRepo->shouldReceive('existsByAnnouncementAndUser')->once()->andReturnFalse();
    $readRepo->shouldReceive('save')->once();

    $useCase = new MarkAnnouncementAsRead($announcementRepo, $readRepo);
    $useCase->execute($announcement->id()->value(), $userId);

    expect(true)->toBeTrue();
});

test('is idempotent when already read', function () {
    $announcement = createPublishedAnnouncementForRead();
    $userId = Uuid::generate()->value();

    $announcementRepo = Mockery::mock(AnnouncementRepositoryInterface::class);
    $announcementRepo->shouldReceive('findById')->once()->andReturn($announcement);

    $readRepo = Mockery::mock(AnnouncementReadRepositoryInterface::class);
    $readRepo->shouldReceive('existsByAnnouncementAndUser')->once()->andReturnTrue();
    $readRepo->shouldNotReceive('save');

    $useCase = new MarkAnnouncementAsRead($announcementRepo, $readRepo);
    $useCase->execute($announcement->id()->value(), $userId);

    expect(true)->toBeTrue();
});

test('throws when announcement not found', function () {
    $announcementRepo = Mockery::mock(AnnouncementRepositoryInterface::class);
    $announcementRepo->shouldReceive('findById')->once()->andReturnNull();

    $readRepo = Mockery::mock(AnnouncementReadRepositoryInterface::class);

    $useCase = new MarkAnnouncementAsRead($announcementRepo, $readRepo);
    $useCase->execute(Uuid::generate()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'Announcement not found');
