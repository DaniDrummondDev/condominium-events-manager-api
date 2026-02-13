<?php

declare(strict_types=1);

namespace Domain\Communication\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class SupportRequestCreated implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $supportRequestId,
        public string $userId,
        public string $subject,
        public string $category,
        public string $priority,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'support_request.created';
    }

    public function aggregateId(): Uuid
    {
        return Uuid::fromString($this->supportRequestId);
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
            'support_request_id' => $this->supportRequestId,
            'user_id' => $this->userId,
            'subject' => $this->subject,
            'category' => $this->category,
            'priority' => $this->priority,
        ];
    }
}
