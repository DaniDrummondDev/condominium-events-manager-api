<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class PlanVersionCreated implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private Uuid $planVersionId,
        private Uuid $planId,
        private int $version,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'billing.plan_version.created';
    }

    public function aggregateId(): Uuid
    {
        return $this->planVersionId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'plan_version_id' => $this->planVersionId->value(),
            'plan_id' => $this->planId->value(),
            'version' => $this->version,
        ];
    }
}
