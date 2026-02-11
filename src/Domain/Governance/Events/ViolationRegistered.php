<?php

declare(strict_types=1);

namespace Domain\Governance\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ViolationRegistered implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $violationId,
        public string $unitId,
        public string $type,
        public string $severity,
        public bool $isAutomatic,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'violation.registered';
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
            'type' => $this->type,
            'severity' => $this->severity,
            'is_automatic' => $this->isAutomatic,
        ];
    }
}
