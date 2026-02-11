<?php

declare(strict_types=1);

namespace Domain\Billing\Entities;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;

class TenantFeatureOverride
{
    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $tenantId,
        private readonly Uuid $featureId,
        private string $value,
        private string $reason,
        private ?DateTimeImmutable $expiresAt,
        private readonly Uuid $createdBy,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public function id(): Uuid
    {
        return $this->id;
    }

    public function tenantId(): Uuid
    {
        return $this->tenantId;
    }

    public function featureId(): Uuid
    {
        return $this->featureId;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdBy(): Uuid
    {
        return $this->createdBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $now >= $this->expiresAt;
    }
}
