<?php

declare(strict_types=1);

namespace Application\Governance\DTOs;

final readonly class RegisterViolationDTO
{
    public function __construct(
        public string $unitId,
        public ?string $tenantUserId,
        public ?string $reservationId,
        public ?string $ruleId,
        public string $type,
        public string $severity,
        public string $description,
        public string $createdBy,
    ) {}
}
