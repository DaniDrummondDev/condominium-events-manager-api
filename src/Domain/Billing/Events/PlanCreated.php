<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class PlanCreated implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private Uuid $planId,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'billing.plan.created';
    }

    public function aggregateId(): Uuid
    {
        return $this->planId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'plan_id' => $this->planId->value(),
        ];
    }
}
