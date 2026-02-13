<?php

declare(strict_types=1);

namespace Application\Communication\Contracts;

use Domain\Communication\Entities\Announcement;
use Domain\Shared\ValueObjects\Uuid;

interface AnnouncementRepositoryInterface
{
    public function findById(Uuid $id): ?Announcement;

    /**
     * @return array<Announcement>
     */
    public function findAll(): array;

    public function save(Announcement $announcement): void;
}
