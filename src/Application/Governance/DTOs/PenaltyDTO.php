<?php

declare(strict_types=1);

namespace Application\Governance\DTOs;

final readonly class PenaltyDTO
{
    public function __construct(
        public string $id,
        public string $violationId,
        public string $unitId,
        public string $type,
        public string $startsAt,
        public ?string $endsAt,
        public string $status,
        public ?string $revokedAt,
        public ?string $revokedBy,
        public ?string $revokedReason,
        public string $createdAt,
    ) {}
}
