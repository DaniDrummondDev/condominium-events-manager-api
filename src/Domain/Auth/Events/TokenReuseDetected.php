<?php

declare(strict_types=1);

namespace Domain\Auth\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class TokenReuseDetected implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private Uuid $userId,
        private Uuid $tokenId,
        private string $ipAddress,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'auth.token.reuse_detected';
    }

    public function aggregateId(): Uuid
    {
        return $this->userId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'user_id' => $this->userId->value(),
            'token_id' => $this->tokenId->value(),
            'ip_address' => $this->ipAddress,
        ];
    }
}
