<?php

declare(strict_types=1);

namespace Domain\Auth\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class AccountLocked implements DomainEvent
{
    public function __construct(
        private Uuid $userId,
        private DateTimeImmutable $lockedUntil,
        private int $failedAttempts,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function eventName(): string
    {
        return 'auth.account.locked';
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
            'locked_until' => $this->lockedUntil->format('c'),
            'failed_attempts' => $this->failedAttempts,
        ];
    }
}
