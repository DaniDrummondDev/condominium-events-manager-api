<?php

declare(strict_types=1);

namespace Application\Communication\UseCases;

use Application\Communication\Contracts\AnnouncementRepositoryInterface;
use Application\Communication\DTOs\AnnouncementDTO;
use Application\Communication\DTOs\CreateAnnouncementDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use DateTimeImmutable;
use Domain\Communication\Entities\Announcement;
use Domain\Communication\Enums\AnnouncementPriority;
use Domain\Communication\Enums\AudienceType;
use Domain\Shared\ValueObjects\Uuid;

final readonly class CreateAnnouncement
{
    public function __construct(
        private AnnouncementRepositoryInterface $announcementRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(CreateAnnouncementDTO $dto): AnnouncementDTO
    {
        $announcement = Announcement::create(
            id: Uuid::generate(),
            title: $dto->title,
            body: $dto->body,
            priority: AnnouncementPriority::from($dto->priority),
            audienceType: AudienceType::from($dto->audienceType),
            audienceIds: $dto->audienceIds,
            publishedBy: Uuid::fromString($dto->publishedBy),
            expiresAt: $dto->expiresAt !== null ? new DateTimeImmutable($dto->expiresAt) : null,
        );

        $this->announcementRepository->save($announcement);
        $this->eventDispatcher->dispatchAll($announcement->pullDomainEvents());

        return self::toDTO($announcement);
    }

    public static function toDTO(Announcement $announcement): AnnouncementDTO
    {
        return new AnnouncementDTO(
            id: $announcement->id()->value(),
            title: $announcement->title(),
            body: $announcement->body(),
            priority: $announcement->priority()->value,
            audienceType: $announcement->audienceType()->value,
            audienceIds: $announcement->audienceIds(),
            status: $announcement->status()->value,
            publishedBy: $announcement->publishedBy()->value(),
            publishedAt: $announcement->publishedAt()->format('c'),
            expiresAt: $announcement->expiresAt()?->format('c'),
            createdAt: $announcement->createdAt()->format('c'),
        );
    }
}
