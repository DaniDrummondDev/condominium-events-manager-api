<?php

declare(strict_types=1);

namespace Domain\Billing\Entities;

use DateTimeImmutable;
use Domain\Billing\Enums\BillingCycle;
use Domain\Billing\Enums\SubscriptionStatus;
use Domain\Billing\Events\SubscriptionActivated;
use Domain\Billing\Events\SubscriptionCanceled;
use Domain\Billing\Events\SubscriptionExpired;
use Domain\Billing\Events\SubscriptionPlanChanged;
use Domain\Billing\Events\SubscriptionRenewed;
use Domain\Billing\Events\SubscriptionSuspended;
use Domain\Billing\ValueObjects\BillingPeriod;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

class Subscription
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $tenantId,
        private Uuid $planVersionId,
        private SubscriptionStatus $status,
        private readonly BillingCycle $billingCycle,
        private BillingPeriod $currentPeriod,
        private ?DateTimeImmutable $gracePeriodEnd = null,
        private ?DateTimeImmutable $canceledAt = null,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $tenantId,
        Uuid $planVersionId,
        BillingCycle $billingCycle,
        BillingPeriod $period,
    ): self {
        $subscription = new self(
            $id,
            $tenantId,
            $planVersionId,
            SubscriptionStatus::Active,
            $billingCycle,
            $period,
        );

        $subscription->domainEvents[] = new SubscriptionActivated(
            $id,
            $tenantId,
            $planVersionId,
        );

        return $subscription;
    }

    public static function createTrialing(
        Uuid $id,
        Uuid $tenantId,
        Uuid $planVersionId,
        BillingCycle $billingCycle,
        BillingPeriod $period,
    ): self {
        return new self(
            $id,
            $tenantId,
            $planVersionId,
            SubscriptionStatus::Trialing,
            $billingCycle,
            $period,
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function tenantId(): Uuid
    {
        return $this->tenantId;
    }

    public function planVersionId(): Uuid
    {
        return $this->planVersionId;
    }

    public function status(): SubscriptionStatus
    {
        return $this->status;
    }

    public function billingCycle(): BillingCycle
    {
        return $this->billingCycle;
    }

    public function currentPeriod(): BillingPeriod
    {
        return $this->currentPeriod;
    }

    public function gracePeriodEnd(): ?DateTimeImmutable
    {
        return $this->gracePeriodEnd;
    }

    public function canceledAt(): ?DateTimeImmutable
    {
        return $this->canceledAt;
    }

    public function activate(): void
    {
        $this->transitionTo(SubscriptionStatus::Active);

        $this->domainEvents[] = new SubscriptionActivated(
            $this->id,
            $this->tenantId,
            $this->planVersionId,
        );
    }

    public function markPastDue(): void
    {
        $this->transitionTo(SubscriptionStatus::PastDue);
    }

    public function enterGracePeriod(DateTimeImmutable $gracePeriodEnd): void
    {
        $this->transitionTo(SubscriptionStatus::GracePeriod);
        $this->gracePeriodEnd = $gracePeriodEnd;
    }

    public function suspend(string $reason = 'non_payment'): void
    {
        $this->transitionTo(SubscriptionStatus::Suspended);

        $this->domainEvents[] = new SubscriptionSuspended(
            $this->id,
            $reason,
        );
    }

    public function cancel(DateTimeImmutable $now): void
    {
        $this->transitionTo(SubscriptionStatus::Canceled);
        $this->canceledAt = $now;

        $this->domainEvents[] = new SubscriptionCanceled(
            $this->id,
            $now,
            $this->currentPeriod->end(),
        );
    }

    public function expire(): void
    {
        $this->transitionTo(SubscriptionStatus::Expired);

        $this->domainEvents[] = new SubscriptionExpired($this->id);
    }

    public function renew(BillingPeriod $newPeriod): void
    {
        if ($this->status !== SubscriptionStatus::Active) {
            throw new DomainException(
                'Only active subscriptions can be renewed',
                'SUBSCRIPTION_NOT_ACTIVE',
                ['subscription_id' => $this->id->value(), 'status' => $this->status->value],
            );
        }

        $this->currentPeriod = $newPeriod;
        $this->gracePeriodEnd = null;

        $this->domainEvents[] = new SubscriptionRenewed(
            $this->id,
            $newPeriod->start(),
            $newPeriod->end(),
        );
    }

    public function changePlan(Uuid $newPlanVersionId): void
    {
        if (! $this->status->isOperational()) {
            throw new DomainException(
                'Cannot change plan for non-operational subscription',
                'SUBSCRIPTION_NOT_OPERATIONAL',
                ['subscription_id' => $this->id->value(), 'status' => $this->status->value],
            );
        }

        $oldPlanVersionId = $this->planVersionId;
        $this->planVersionId = $newPlanVersionId;

        $this->domainEvents[] = new SubscriptionPlanChanged(
            $this->id,
            $oldPlanVersionId,
            $newPlanVersionId,
        );
    }

    public function reactivate(): void
    {
        $this->transitionTo(SubscriptionStatus::Active);
        $this->gracePeriodEnd = null;

        $this->domainEvents[] = new SubscriptionActivated(
            $this->id,
            $this->tenantId,
            $this->planVersionId,
        );
    }

    /**
     * @return array<object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    private function transitionTo(SubscriptionStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new DomainException(
                "Cannot transition subscription from '{$this->status->value}' to '{$target->value}'",
                'INVALID_SUBSCRIPTION_TRANSITION',
                [
                    'subscription_id' => $this->id->value(),
                    'current_status' => $this->status->value,
                    'target_status' => $target->value,
                    'allowed' => array_map(
                        fn (SubscriptionStatus $s) => $s->value,
                        $this->status->allowedTransitions(),
                    ),
                ],
            );
        }

        $this->status = $target;
    }
}
