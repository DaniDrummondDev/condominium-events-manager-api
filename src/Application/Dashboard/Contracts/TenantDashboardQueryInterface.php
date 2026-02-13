<?php

declare(strict_types=1);

namespace Application\Dashboard\Contracts;

use Application\Dashboard\DTOs\ResidentDashboardDTO;
use Application\Dashboard\DTOs\TenantDashboardDTO;
use Domain\Shared\ValueObjects\Uuid;

interface TenantDashboardQueryInterface
{
    public function getAdminDashboard(): TenantDashboardDTO;

    public function getResidentDashboard(Uuid $userId): ResidentDashboardDTO;
}
