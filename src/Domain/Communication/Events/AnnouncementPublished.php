<?php

declare(strict_types=1);

namespace Domain\Communication\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class AnnouncementPublished implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $announcementId,
        public string $title,
        public string $priority,
        public string $audienceType,
        public string $publishedBy,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'announcement.published';
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
            'priority' => $this->priority,
            'audience_type' => $this->audienceType,
            'published_by' => $this->publishedBy,
        ];
    }
}
