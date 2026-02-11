<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class SubscriptionActivated implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private Uuid $subscriptionId,
        private Uuid $tenantId,
        private Uuid $planVersionId,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'billing.subscription.activated';
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
            'tenant_id' => $this->tenantId->value(),
            'plan_version_id' => $this->planVersionId->value(),
        ];
    }
}
