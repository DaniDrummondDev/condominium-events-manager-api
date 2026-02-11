<?php

declare(strict_types=1);

namespace Application\Governance\Contracts;

use Domain\Governance\Entities\Penalty;
use Domain\Shared\ValueObjects\Uuid;

interface PenaltyRepositoryInterface
{
    public function findById(Uuid $id): ?Penalty;

    /**
     * @return array<Penalty>
     */
    public function findByUnit(Uuid $unitId): array;

    /**
     * Find active penalties for a unit (status=active and not expired).
     *
     * @return array<Penalty>
     */
    public function findActiveByUnit(Uuid $unitId): array;

    /**
     * Check if a unit has an active blocking penalty.
     * Used by CreateReservation to prevent booking when unit is blocked.
     */
    public function hasActiveBlock(Uuid $unitId): bool;

    public function save(Penalty $penalty): void;
}
