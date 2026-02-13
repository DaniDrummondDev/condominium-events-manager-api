<?php

declare(strict_types=1);

namespace Domain\Communication\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class AnnouncementArchived implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $announcementId,
        public string $title,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'announcement.archived';
    }

    public function aggregateId(): Uuid
    {
        return Uuid::fromString($this->announcementId);
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
            'announcement_id' => $this->announcementId,
            'title' => $this->title,
        ];
    }
}
