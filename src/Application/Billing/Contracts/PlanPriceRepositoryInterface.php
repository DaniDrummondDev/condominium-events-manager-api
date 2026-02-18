<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use Domain\Billing\Entities\PlanPrice;
use Domain\Billing\Enums\BillingCycle;
use Domain\Shared\ValueObjects\Uuid;

interface PlanPriceRepositoryInterface
{
    /**
     * @return array<PlanPrice>
     */
    public function findByPlanVersionId(Uuid $planVersionId): array;

    public function findByPlanVersionIdAndBillingCycle(Uuid $planVersionId, BillingCycle $billingCycle): ?PlanPrice;

    public function save(PlanPrice $planPrice): void;

    /**
     * @param  array<PlanPrice>  $planPrices
     */
    public function saveMany(array $planPrices): void;
}
