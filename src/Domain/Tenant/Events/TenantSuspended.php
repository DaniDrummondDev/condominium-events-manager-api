<?php

declare(strict_types=1);

namespace Domain\Tenant\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class TenantSuspended implements DomainEvent
{
    public function __construct(
        private Uuid $tenantId,
        private string $reason,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function eventName(): string
    {
        return 'tenant.suspended';
    }

    public function aggregateId(): Uuid
    {
        return $this->tenantId;
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
            'tenant_id' => $this->tenantId->value(),
            'reason' => $this->reason,
        ];
    }
}
