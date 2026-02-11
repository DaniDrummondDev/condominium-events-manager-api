<?php

declare(strict_types=1);

namespace Application\Auth\DTOs;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;

final readonly class RefreshTokenRecord
{
    public function __construct(
        public Uuid $id,
        public Uuid $userId,
        public string $tokenHash,
        public ?Uuid $parentId,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $usedAt,
        public ?DateTimeImmutable $revokedAt,
        public string $ipAddress,
        public string $userAgent,
        public DateTimeImmutable $createdAt,
    ) {}

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }
}
