<?php

declare(strict_types=1);

namespace Application\Auth\Contracts;

interface TenantConnectionManagerInterface
{
    public function switchToTenant(string $databaseName): void;

    public function resetConnection(): void;
}
