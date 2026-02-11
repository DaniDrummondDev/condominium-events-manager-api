<?php

declare(strict_types=1);

namespace Application\Governance\DTOs;

final readonly class ContestViolationDTO
{
    public function __construct(
        public string $violationId,
        public string $tenantUserId,
        public string $reason,
    ) {}
}
