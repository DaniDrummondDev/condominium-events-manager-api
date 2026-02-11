<?php

declare(strict_types=1);

namespace Domain\Governance\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class PenaltyExpired implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $penaltyId,
        public string $unitId,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'penalty.expired';
    }

    public function aggregateId(): Uuid
    {
        return Uuid::fromString($this->penaltyId);
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'penalty_id' => $this->penaltyId,
            'unit_id' => $this->unitId,
        ];
    }
}
