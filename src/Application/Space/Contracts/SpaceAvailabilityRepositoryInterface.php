<?php

declare(strict_types=1);

namespace Application\Space\Contracts;

use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceAvailability;

interface SpaceAvailabilityRepositoryInterface
{
    /**
     * @return array<SpaceAvailability>
     */
    public function findBySpaceId(Uuid $spaceId): array;

    /**
     * @return array<SpaceAvailability>
     */
    public function findBySpaceIdAndDay(Uuid $spaceId, int $dayOfWeek): array;

    public function findById(Uuid $id): ?SpaceAvailability;

    public function save(SpaceAvailability $availability): void;

    public function delete(Uuid $id): void;
}
