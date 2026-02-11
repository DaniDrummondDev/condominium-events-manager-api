<?php

declare(strict_types=1);

namespace Domain\Unit\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ResidentDeactivated implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $residentId,
        public string $unitId,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'unit.resident.deactivated';
    }

    public function aggregateId(): Uuid
    {
        return Uuid::fromString($this->residentId);
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'resident_id' => $this->residentId,
            'unit_id' => $this->unitId,
        ];
    }
}
