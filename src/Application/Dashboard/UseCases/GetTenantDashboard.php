<?php

declare(strict_types=1);

namespace Application\Dashboard\UseCases;

use Application\Dashboard\Contracts\TenantDashboardQueryInterface;
use Application\Dashboard\DTOs\TenantDashboardDTO;

final readonly class GetTenantDashboard
{
    public function __construct(
        private TenantDashboardQueryInterface $query,
    ) {}

    public function execute(): TenantDashboardDTO
    {
        return $this->query->getAdminDashboard();
    }
}
