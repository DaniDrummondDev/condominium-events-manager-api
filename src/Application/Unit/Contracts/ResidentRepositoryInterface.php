<?php

declare(strict_types=1);

namespace Application\Unit\Contracts;

use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Resident;

interface ResidentRepositoryInterface
{
    public function findById(Uuid $id): ?Resident;

    /**
     * @return array<Resident>
     */
    public function findByUnitId(Uuid $unitId): array;

    /**
     * @return array<Resident>
     */
    public function findActiveByUnitId(Uuid $unitId): array;

    public function countActiveByUnitId(Uuid $unitId): int;

    /**
     * @return array<Resident>
     */
    public function findByTenantUserId(Uuid $tenantUserId): array;

    public function save(Resident $resident): void;
}
