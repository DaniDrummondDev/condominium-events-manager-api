<?php

declare(strict_types=1);

namespace Domain\Billing\Entities;

use DateTimeImmutable;
use Domain\Billing\Enums\BillingCycle;
use Domain\Billing\Enums\PlanStatus;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

class PlanVersion
{
    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $planId,
        private readonly int $version,
        private readonly Money $price,
        private readonly BillingCycle $billingCycle,
        private readonly int $trialDays,
        private PlanStatus $status,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public function id(): Uuid
    {
        return $this->id;
    }

    public function planId(): Uuid
    {
        return $this->planId;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function billingCycle(): BillingCycle
    {
        return $this->billingCycle;
    }

    public function trialDays(): int
    {
        return $this->trialDays;
    }

    public function status(): PlanStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->status->isAvailable();
    }

    public function hasTrialPeriod(): bool
    {
        return $this->trialDays > 0;
    }

    public function deactivate(): void
    {
        $this->status = PlanStatus::Inactive;
    }
}
