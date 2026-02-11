<?php

declare(strict_types=1);

namespace App\Infrastructure\MultiTenancy;

use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenantManager
{
    private ?TenantContext $currentContext = null;

    /**
     * Resolve tenant pelo ID, configura conexao e retorna contexto.
     */
    public function resolve(string $tenantId): TenantContext
    {
        $tenant = TenantModel::query()->find($tenantId);

        if ($tenant === null) {
            throw new \RuntimeException("Tenant not found: {$tenantId}");
        }

        return $this->setTenant($tenant);
    }

    /**
     * Resolve tenant pelo slug.
     */
    public function resolveBySlug(string $slug): TenantContext
    {
        $tenant = TenantModel::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            throw new \RuntimeException("Tenant not found: {$slug}");
        }

        return $this->setTenant($tenant);
    }

    /**
     * Configura conexao dinamica para um database de tenant.
     */
    public function switchConnection(string $databaseName): void
    {
        Config::set('database.connections.tenant.database', $databaseName);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    /**
     * Reseta a conexao tenant (limpa estado).
     */
    public function resetConnection(): void
    {
        Config::set('database.connections.tenant.database', null);
        DB::purge('tenant');
        $this->currentContext = null;
    }

    public function currentContext(): ?TenantContext
    {
        return $this->currentContext;
    }

    public function isResolved(): bool
    {
        return $this->currentContext !== null;
    }

    private function setTenant(TenantModel $tenant): TenantContext
    {
        $databaseName = $tenant->database_name ?? 'tenant_'.$tenant->slug;

        $this->switchConnection($databaseName);

        $this->currentContext = new TenantContext(
            tenantId: $tenant->id,
            tenantSlug: $tenant->slug,
            tenantName: $tenant->name,
            tenantType: $tenant->type,
            tenantStatus: $tenant->status,
            databaseName: $databaseName,
            resolvedAt: new DateTimeImmutable,
        );

        return $this->currentContext;
    }
}
