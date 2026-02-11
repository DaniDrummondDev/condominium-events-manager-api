<?php

declare(strict_types=1);

namespace App\Interface\Console\Commands;

use App\Infrastructure\MultiTenancy\TenantManager;
use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunTenantMigrationsCommand extends Command
{
    protected $signature = 'tenant:migrate
        {--tenant= : Slug do tenant especifico}
        {--seed : Executar seeders apos migrations}
        {--fresh : Drop e recria todas as tabelas}';

    protected $description = 'Executa migrations para todos os tenants ativos ou para um tenant especifico';

    public function handle(TenantManager $tenantManager): int
    {
        $tenantSlug = $this->option('tenant');

        if ($tenantSlug) {
            return $this->migrateOne($tenantManager, $tenantSlug);
        }

        return $this->migrateAll($tenantManager);
    }

    private function migrateOne(TenantManager $tenantManager, string $slug): int
    {
        $tenant = TenantModel::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            $this->error("Tenant '{$slug}' not found.");

            return self::FAILURE;
        }

        if (! $tenant->database_name) {
            $this->error("Tenant '{$slug}' has no database assigned.");

            return self::FAILURE;
        }

        return $this->runMigration($tenantManager, $tenant);
    }

    private function migrateAll(TenantManager $tenantManager): int
    {
        $tenants = TenantModel::query()
            ->whereIn('status', ['active', 'trial', 'past_due'])
            ->whereNotNull('database_name')
            ->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found for migration.');

            return self::SUCCESS;
        }

        $this->info("Migrating {$tenants->count()} tenant(s)...");
        $this->newLine();

        $failed = 0;

        foreach ($tenants as $tenant) {
            $result = $this->runMigration($tenantManager, $tenant);
            if ($result !== self::SUCCESS) {
                $failed++;
            }
        }

        $this->newLine();

        if ($failed > 0) {
            $this->warn("{$failed} tenant(s) failed migration.");

            return self::FAILURE;
        }

        $this->info('All tenant migrations completed successfully.');

        return self::SUCCESS;
    }

    private function runMigration(TenantManager $tenantManager, TenantModel $tenant): int
    {
        $this->info("  [{$tenant->slug}] Migrating database '{$tenant->database_name}'...");

        try {
            $tenantManager->switchConnection($tenant->database_name);

            $command = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

            Artisan::call($command, [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            if ($this->option('seed')) {
                Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => 'Database\\Seeders\\Tenant\\TenantSeeder',
                    '--force' => true,
                ]);
            }

            $this->info("  [{$tenant->slug}] OK");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("  [{$tenant->slug}] FAILED: {$e->getMessage()}");

            return self::FAILURE;
        } finally {
            $tenantManager->resetConnection();
        }
    }
}
