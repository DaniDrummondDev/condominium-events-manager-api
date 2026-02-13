<?php

declare(strict_types=1);

namespace Application\Communication\UseCases;

use Application\Communication\Contracts\AnnouncementRepositoryInterface;
use Application\Communication\DTOs\AnnouncementDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ArchiveAnnouncement
{
    public function __construct(
        private AnnouncementRepositoryInterface $announcementRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(string $announcementId): AnnouncementDTO
    {
        $announcement = $this->announcementRepository->findById(Uuid::fromString($announcementId));

        if ($announcement === null) {
            throw new DomainException(
                'Announcement not found',
                'ANNOUNCEMENT_NOT_FOUND',
                ['announcement_id' => $announcementId],
            );
        }

        $announcement->archive();

        $this->announcementRepository->save($announcement);
        $this->eventDispatcher->dispatchAll($announcement->pullDomainEvents());

        return CreateAnnouncement::toDTO($announcement);
    }
}
