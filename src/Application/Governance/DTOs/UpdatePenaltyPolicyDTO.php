<?php

declare(strict_types=1);

namespace Application\Governance\DTOs;

final readonly class UpdatePenaltyPolicyDTO
{
    public function __construct(
        public string $policyId,
        public ?int $occurrenceThreshold,
        public ?string $penaltyType,
        public ?int $blockDays,
    ) {}
}
