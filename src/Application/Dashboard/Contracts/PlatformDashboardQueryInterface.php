<?php

declare(strict_types=1);

namespace Application\Dashboard\Contracts;

use Application\Dashboard\DTOs\PlatformDashboardDTO;
use Application\Dashboard\DTOs\TenantMetricsDTO;

interface PlatformDashboardQueryInterface
{
    public function getPlatformDashboard(): PlatformDashboardDTO;

    public function getTenantMetrics(string $tenantId): ?TenantMetricsDTO;
}
