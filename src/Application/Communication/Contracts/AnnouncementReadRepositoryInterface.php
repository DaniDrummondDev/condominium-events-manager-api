<?php

declare(strict_types=1);

namespace Application\Communication\Contracts;

use Domain\Communication\Entities\AnnouncementRead;
use Domain\Shared\ValueObjects\Uuid;

interface AnnouncementReadRepositoryInterface
{
    public function existsByAnnouncementAndUser(Uuid $announcementId, Uuid $userId): bool;

    public function countByAnnouncement(Uuid $announcementId): int;

    public function save(AnnouncementRead $read): void;
}
