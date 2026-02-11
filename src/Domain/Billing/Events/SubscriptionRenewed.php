<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class SubscriptionRenewed implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private Uuid $subscriptionId,
        private DateTimeImmutable $newPeriodStart,
        private DateTimeImmutable $newPeriodEnd,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'billing.subscription.renewed';
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
            'new_period_start' => $this->newPeriodStart->format('c'),
            'new_period_end' => $this->newPeriodEnd->format('c'),
        ];
    }
}
