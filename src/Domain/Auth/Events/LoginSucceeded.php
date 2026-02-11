<?php

declare(strict_types=1);

namespace Domain\Auth\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class LoginSucceeded implements DomainEvent
{
    public function __construct(
        private Uuid $userId,
        private string $role,
        private ?Uuid $tenantId,
        private string $ipAddress,
        private string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function eventName(): string
    {
        return 'auth.login.succeeded';
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
            'role' => $this->role,
            'tenant_id' => $this->tenantId?->value(),
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
