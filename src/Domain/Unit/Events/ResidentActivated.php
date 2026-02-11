<?php

declare(strict_types=1);

namespace Domain\Unit\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ResidentActivated implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $residentId,
        public string $tenantUserId,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'unit.resident.activated';
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
            'tenant_user_id' => $this->tenantUserId,
        ];
    }
}
