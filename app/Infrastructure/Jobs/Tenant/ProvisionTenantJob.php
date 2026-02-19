<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Tenant;

use App\Infrastructure\MultiTenancy\TenantDatabaseCreator;
use App\Infrastructure\MultiTenancy\TenantManager;
use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use App\Infrastructure\Persistence\Tenant\Models\TenantUserModel;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $tenantId,
    ) {
        $this->queue = 'tenant-provisioning';
    }

    public function handle(
        TenantDatabaseCreator $databaseCreator,
        TenantManager $tenantManager,
    ): void {
        $tenant = TenantModel::query()->find($this->tenantId);

        if ($tenant === null) {
            Log::error('ProvisionTenantJob: tenant not found', ['tenant_id' => $this->tenantId]);

            return;
        }

        if ($tenant->status !== 'provisioning') {
            Log::info('ProvisionTenantJob: tenant not in provisioning state, skipping', [
                'tenant_id' => $this->tenantId,
                'status' => $tenant->status,
            ]);

            return;
        }

        $databaseName = $tenant->database_name ?? 'tenant_'.$tenant->slug;

        Log::info('ProvisionTenantJob: starting provisioning', [
            'tenant_id' => $this->tenantId,
            'database' => $databaseName,
        ]);

        // 1. Cria database
        $databaseCreator->createDatabase($databaseName);

        // 2. Configura conexao para o novo database
        $tenantManager->switchConnection($databaseName);

        // 3. Executa migrations do tenant
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        // 4. Cria usuario admin (sindico) se config contem dados
        $config = $tenant->config;
        if (is_array($config) && isset($config['admin_email'])) {
            TenantUserModel::query()->create([
                'id' => Uuid::generate()->value(),
                'email' => $config['admin_email'],
                'name' => $config['admin_name'],
                'password_hash' => $config['admin_password_hash'],
                'phone' => $config['admin_phone'] ?? null,
                'role' => 'sindico',
                'status' => 'active',
            ]);

            Log::info('ProvisionTenantJob: admin user created', [
                'tenant_id' => $this->tenantId,
                'email' => $config['admin_email'],
            ]);
        }

        // 5. Atualiza tenant para active e limpa config (seguranca)
        $tenant->update([
            'database_name' => $databaseName,
            'status' => 'active',
            'provisioned_at' => now(),
            'config' => null,
        ]);

        // 6. Reseta conexao
        $tenantManager->resetConnection();

        Log::info('ProvisionTenantJob: provisioning completed', [
            'tenant_id' => $this->tenantId,
            'database' => $databaseName,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProvisionTenantJob: provisioning failed', [
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }
}
