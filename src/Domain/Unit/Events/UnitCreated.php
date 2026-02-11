<?php

declare(strict_types=1);

namespace Domain\Unit\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class UnitCreated implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $unitId,
        public ?string $blockId,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'unit.created';
    }

    public function aggregateId(): Uuid
    {
        return Uuid::fromString($this->unitId);
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'unit_id' => $this->unitId,
            'block_id' => $this->blockId,
        ];
    }
}
