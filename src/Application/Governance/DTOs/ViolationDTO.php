<?php

declare(strict_types=1);

namespace Application\Governance\DTOs;

final readonly class ViolationDTO
{
    public function __construct(
        public string $id,
        public string $unitId,
        public ?string $tenantUserId,
        public ?string $reservationId,
        public ?string $ruleId,
        public string $type,
        public string $severity,
        public string $description,
        public string $status,
        public bool $isAutomatic,
        public ?string $createdBy,
        public ?string $upheldBy,
        public ?string $upheldAt,
        public ?string $revokedBy,
        public ?string $revokedAt,
        public ?string $revokedReason,
        public string $createdAt,
    ) {}
}
