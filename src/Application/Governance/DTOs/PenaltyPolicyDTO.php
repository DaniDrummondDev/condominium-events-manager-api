<?php

declare(strict_types=1);

namespace Application\Governance\DTOs;

final readonly class PenaltyPolicyDTO
{
    public function __construct(
        public string $id,
        public string $violationType,
        public int $occurrenceThreshold,
        public string $penaltyType,
        public ?int $blockDays,
        public bool $isActive,
        public string $createdAt,
    ) {}
}
