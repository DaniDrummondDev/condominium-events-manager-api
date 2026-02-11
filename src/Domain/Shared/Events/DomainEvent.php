<?php

declare(strict_types=1);

namespace Domain\Shared\Events;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;

interface DomainEvent
{
    public function eventName(): string;

    public function aggregateId(): Uuid;

    public function occurredAt(): DateTimeImmutable;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;
}
