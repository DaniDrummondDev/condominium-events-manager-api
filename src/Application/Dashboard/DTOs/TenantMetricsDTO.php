<?php

declare(strict_types=1);

namespace Application\Dashboard\DTOs;

final readonly class TenantMetricsDTO
{
    public function __construct(
        public string $tenantId,
        public int $unitsCount,
        public int $usersCount,
        public int $spacesCount,
        public int $reservationsThisMonth,
        public int $activeViolations,
        public string $measuredAt,
    ) {}
}
