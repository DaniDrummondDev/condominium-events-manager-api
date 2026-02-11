<?php

declare(strict_types=1);

namespace Domain\Unit\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class BlockCreated implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $blockId,
        public string $tenantId,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'unit.block.created';
    }

    public function aggregateId(): Uuid
    {
        return Uuid::fromString($this->blockId);
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'block_id' => $this->blockId,
            'tenant_id' => $this->tenantId,
        ];
    }
}
