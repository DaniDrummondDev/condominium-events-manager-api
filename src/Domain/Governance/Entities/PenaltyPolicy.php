<?php

declare(strict_types=1);

namespace Domain\Governance\Entities;

use DateTimeImmutable;
use Domain\Governance\Enums\PenaltyType;
use Domain\Governance\Enums\ViolationType;
use Domain\Shared\ValueObjects\Uuid;

class PenaltyPolicy
{
    public function __construct(
        private readonly Uuid $id,
        private ViolationType $violationType,
        private int $occurrenceThreshold,
        private PenaltyType $penaltyType,
        private ?int $blockDays,
        private bool $isActive,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        ViolationType $violationType,
        int $occurrenceThreshold,
        PenaltyType $penaltyType,
        ?int $blockDays,
    ): self {
        return new self(
            id: $id,
            violationType: $violationType,
            occurrenceThreshold: $occurrenceThreshold,
            penaltyType: $penaltyType,
            blockDays: $blockDays,
            isActive: true,
            createdAt: new DateTimeImmutable,
        );
    }

    public function update(
        int $occurrenceThreshold,
        PenaltyType $penaltyType,
        ?int $blockDays,
    ): void {
        $this->occurrenceThreshold = $occurrenceThreshold;
        $this->penaltyType = $penaltyType;
        $this->blockDays = $blockDays;
    }

    public function matches(ViolationType $type): bool
    {
        return $this->isActive && $this->violationType === $type;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function violationType(): ViolationType
    {
        return $this->violationType;
    }

    public function occurrenceThreshold(): int
    {
        return $this->occurrenceThreshold;
    }

    public function penaltyType(): PenaltyType
    {
        return $this->penaltyType;
    }

    public function blockDays(): ?int
    {
        return $this->blockDays;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
