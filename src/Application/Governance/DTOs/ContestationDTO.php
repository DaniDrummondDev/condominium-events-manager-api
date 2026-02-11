<?php

declare(strict_types=1);

namespace Application\Governance\DTOs;

final readonly class ContestationDTO
{
    public function __construct(
        public string $id,
        public string $violationId,
        public string $tenantUserId,
        public string $reason,
        public string $status,
        public ?string $response,
        public ?string $respondedBy,
        public ?string $respondedAt,
        public string $createdAt,
    ) {}
}
