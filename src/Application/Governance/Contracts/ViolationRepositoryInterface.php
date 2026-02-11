<?php

declare(strict_types=1);

namespace Application\Governance\Contracts;

use Domain\Governance\Entities\Violation;
use Domain\Governance\Enums\ViolationType;
use Domain\Shared\ValueObjects\Uuid;

interface ViolationRepositoryInterface
{
    public function findById(Uuid $id): ?Violation;

    /**
     * @return array<Violation>
     */
    public function findByUnit(Uuid $unitId): array;

    /**
     * @return array<Violation>
     */
    public function findByResident(Uuid $tenantUserId): array;

    /**
     * Count violations for a unit of a specific type within a number of days.
     * Used for penalty policy threshold checking.
     */
    public function countByUnitAndType(Uuid $unitId, ViolationType $type, int $withinDays): int;

    public function save(Violation $violation): void;

    public function delete(Uuid $id): void;
}
