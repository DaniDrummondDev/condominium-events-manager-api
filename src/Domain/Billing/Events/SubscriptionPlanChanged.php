<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class SubscriptionPlanChanged implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private Uuid $subscriptionId,
        private Uuid $oldPlanVersionId,
        private Uuid $newPlanVersionId,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'billing.subscription.plan_changed';
    }

    public function aggregateId(): Uuid
    {
        return $this->subscriptionId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'subscription_id' => $this->subscriptionId->value(),
            'old_plan_version_id' => $this->oldPlanVersionId->value(),
            'new_plan_version_id' => $this->newPlanVersionId->value(),
        ];
    }
}
