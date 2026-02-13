<?php

declare(strict_types=1);

namespace Application\Dashboard\UseCases;

use Application\Dashboard\Contracts\TenantDashboardQueryInterface;
use Application\Dashboard\DTOs\ResidentDashboardDTO;
use Domain\Shared\ValueObjects\Uuid;

final readonly class GetResidentDashboard
{
    public function __construct(
        private TenantDashboardQueryInterface $query,
    ) {}

    public function execute(string $userId): ResidentDashboardDTO
    {
        return $this->query->getResidentDashboard(Uuid::fromString($userId));
    }
}
