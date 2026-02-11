<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use Domain\Billing\Entities\PlanVersion;
use Domain\Shared\ValueObjects\Uuid;

interface PlanVersionRepositoryInterface
{
    public function findById(Uuid $id): ?PlanVersion;

    public function findActiveByPlanId(Uuid $planId): ?PlanVersion;

    public function findByPlanIdAndVersion(Uuid $planId, int $version): ?PlanVersion;

    public function nextVersionNumber(Uuid $planId): int;

    public function save(PlanVersion $planVersion): void;
}
