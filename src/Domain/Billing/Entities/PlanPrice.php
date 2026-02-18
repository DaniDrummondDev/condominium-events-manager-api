<?php

declare(strict_types=1);

namespace Domain\Billing\Entities;

use Domain\Billing\Enums\BillingCycle;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

final readonly class PlanPrice
{
    public function __construct(
        private Uuid $id,
        private Uuid $planVersionId,
        private BillingCycle $billingCycle,
        private Money $price,
        private int $trialDays,
    ) {}

    public function id(): Uuid
    {
        return $this->id;
    }

    public function planVersionId(): Uuid
    {
        return $this->planVersionId;
    }

    public function billingCycle(): BillingCycle
    {
        return $this->billingCycle;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function trialDays(): int
    {
        return $this->trialDays;
    }

    public function hasTrialPeriod(): bool
    {
        return $this->trialDays > 0;
    }
}
