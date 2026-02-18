<?php

declare(strict_types=1);

namespace Domain\Billing\Entities;

use DateTimeImmutable;
use Domain\Billing\Enums\PlanStatus;
use Domain\Shared\ValueObjects\Uuid;

class PlanVersion
{
    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $planId,
        private readonly int $version,
        private PlanStatus $status,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public function id(): Uuid
    {
        return $this->id;
    }

    public function planId(): Uuid
    {
        return $this->planId;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function status(): PlanStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->status->isAvailable();
    }

    public function deactivate(): void
    {
        $this->status = PlanStatus::Inactive;
    }
}
