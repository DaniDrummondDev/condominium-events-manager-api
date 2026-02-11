<?php

declare(strict_types=1);

namespace Domain\Governance\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class PenaltyApplied implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $penaltyId,
        public string $violationId,
        public string $unitId,
        public string $type,
        public string $startsAt,
        public ?string $endsAt,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'penalty.applied';
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
            'violation_id' => $this->violationId,
            'unit_id' => $this->unitId,
            'type' => $this->type,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ];
    }
}
