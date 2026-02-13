<?php

declare(strict_types=1);

namespace Application\Dashboard\UseCases;

use Application\Dashboard\Contracts\PlatformDashboardQueryInterface;
use Application\Dashboard\DTOs\TenantMetricsDTO;
use Domain\Shared\Exceptions\DomainException;

final readonly class GetTenantMetrics
{
    public function __construct(
        private PlatformDashboardQueryInterface $query,
    ) {}

    public function execute(string $tenantId): TenantMetricsDTO
    {
        $metrics = $this->query->getTenantMetrics($tenantId);

        if ($metrics === null) {
            throw new DomainException('Tenant not found', 'TENANT_NOT_FOUND', ['tenant_id' => $tenantId]);
        }

        return $metrics;
    }
}
