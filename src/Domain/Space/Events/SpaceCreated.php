<?php

declare(strict_types=1);

namespace Domain\Space\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class SpaceCreated implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $spaceId,
        public string $name,
        public string $type,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'space.created';
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
            'name' => $this->name,
            'type' => $this->type,
        ];
    }
}
