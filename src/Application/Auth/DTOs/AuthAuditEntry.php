<?php

declare(strict_types=1);

namespace Application\Auth\DTOs;

use DateTimeImmutable;
use Domain\Auth\Enums\AuthAuditEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class AuthAuditEntry
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public AuthAuditEvent $eventType,
        public ?Uuid $actorId,
        public ?Uuid $tenantId,
        public string $ipAddress,
        public string $userAgent,
        public array $metadata,
        public DateTimeImmutable $occurredAt,
    ) {}
}
