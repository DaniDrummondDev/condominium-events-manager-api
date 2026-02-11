<?php

declare(strict_types=1);

namespace Application\Governance\DTOs;

final readonly class RevokePenaltyDTO
{
    public function __construct(
        public string $penaltyId,
        public string $revokedBy,
        public string $reason,
    ) {}
}
