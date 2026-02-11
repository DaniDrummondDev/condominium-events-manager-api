<?php

declare(strict_types=1);

namespace Domain\Unit\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ResidentInvited implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $residentId,
        public string $unitId,
        public string $email,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'unit.resident.invited';
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
            'email' => $this->email,
        ];
    }
}
