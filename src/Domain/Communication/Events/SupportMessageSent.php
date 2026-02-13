<?php

declare(strict_types=1);

namespace Domain\Communication\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class SupportMessageSent implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $messageId,
        public string $supportRequestId,
        public string $senderId,
        public bool $isInternal,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'support_message.sent';
    }

    public function aggregateId(): Uuid
    {
        return Uuid::fromString($this->messageId);
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
            'message_id' => $this->messageId,
            'support_request_id' => $this->supportRequestId,
            'sender_id' => $this->senderId,
            'is_internal' => $this->isInternal,
        ];
    }
}
