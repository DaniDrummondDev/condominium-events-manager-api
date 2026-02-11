<?php

declare(strict_types=1);

namespace Domain\Space\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class SpaceBlocked implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $spaceId,
        public string $blockId,
        public string $reason,
        public string $startDatetime,
        public string $endDatetime,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'space.blocked';
    }

    public function aggregateId(): Uuid
    {
        return Uuid::fromString($this->spaceId);
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'space_id' => $this->spaceId,
            'block_id' => $this->blockId,
            'reason' => $this->reason,
            'start_datetime' => $this->startDatetime,
            'end_datetime' => $this->endDatetime,
        ];
    }
}
