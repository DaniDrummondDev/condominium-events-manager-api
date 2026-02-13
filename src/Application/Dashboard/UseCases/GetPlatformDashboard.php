<?php

declare(strict_types=1);

namespace Application\Dashboard\UseCases;

use Application\Dashboard\Contracts\PlatformDashboardQueryInterface;
use Application\Dashboard\DTOs\PlatformDashboardDTO;

final readonly class GetPlatformDashboard
{
    public function __construct(
        private PlatformDashboardQueryInterface $query,
    ) {}

    public function execute(): PlatformDashboardDTO
    {
        return $this->query->getPlatformDashboard();
    }
}
