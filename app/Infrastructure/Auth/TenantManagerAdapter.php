<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Infrastructure\MultiTenancy\TenantManager;
use Application\Auth\Contracts\TenantConnectionManagerInterface;

class TenantManagerAdapter implements TenantConnectionManagerInterface
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    public function switchToTenant(string $databaseName): void
    {
        $this->tenantManager->switchConnection($databaseName);
    }

    public function resetConnection(): void
    {
        $this->tenantManager->resetConnection();
    }
}
