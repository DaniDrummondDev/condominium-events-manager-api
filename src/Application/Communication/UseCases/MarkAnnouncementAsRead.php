<?php

declare(strict_types=1);

namespace Application\Communication\UseCases;

use Application\Communication\Contracts\AnnouncementReadRepositoryInterface;
use Application\Communication\Contracts\AnnouncementRepositoryInterface;
use Domain\Communication\Entities\AnnouncementRead;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class MarkAnnouncementAsRead
{
    public function __construct(
        private AnnouncementRepositoryInterface $announcementRepository,
        private AnnouncementReadRepositoryInterface $readRepository,
    ) {}

    public function execute(string $announcementId, string $userId): void
    {
        $announcement = $this->announcementRepository->findById(Uuid::fromString($announcementId));

        if ($announcement === null) {
            throw new DomainException(
                'Announcement not found',
                'ANNOUNCEMENT_NOT_FOUND',
                ['announcement_id' => $announcementId],
            );
        }

        $announcementUuid = Uuid::fromString($announcementId);
        $userUuid = Uuid::fromString($userId);

        if ($this->readRepository->existsByAnnouncementAndUser($announcementUuid, $userUuid)) {
            return;
        }

        $read = AnnouncementRead::create(
            id: Uuid::generate(),
            announcementId: $announcementUuid,
            userId: $userUuid,
        );

        $this->readRepository->save($read);
    }
}
