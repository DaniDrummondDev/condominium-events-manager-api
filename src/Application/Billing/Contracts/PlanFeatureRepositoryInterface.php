<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use Domain\Billing\Entities\PlanFeature;
use Domain\Shared\ValueObjects\Uuid;

interface PlanFeatureRepositoryInterface
{
    /**
     * @return array<PlanFeature>
     */
    public function findByPlanVersionId(Uuid $planVersionId): array;

    public function save(PlanFeature $planFeature): void;

    /**
     * @param  array<PlanFeature>  $planFeatures
     */
    public function saveMany(array $planFeatures): void;
}
