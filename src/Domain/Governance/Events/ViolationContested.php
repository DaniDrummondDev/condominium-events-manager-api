<?php

declare(strict_types=1);

namespace Domain\Governance\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ViolationContested implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $violationId,
        public string $unitId,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'violation.contested';
    }

    public function aggregateId(): Uuid
    {
        return Uuid::fromString($this->violationId);
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
            'violation_id' => $this->violationId,
            'unit_id' => $this->unitId,
        ];
    }
}
