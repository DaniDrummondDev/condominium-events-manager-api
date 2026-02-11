<?php

declare(strict_types=1);

namespace App\Interface\Console\Commands;

use App\Infrastructure\MultiTenancy\TenantDatabaseCreator;
use App\Infrastructure\MultiTenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SetupDevelopmentCommand extends Command
{
    protected $signature = 'dev:setup {--fresh : Drop e recria tudo do zero}';

    protected $description = 'Setup completo do ambiente de desenvolvimento (migrations + seeds)';

    public function handle(TenantDatabaseCreator $dbCreator, TenantManager $tenantManager): int
    {
        $this->info('');
        $this->info('=== Condominium Events Manager — Dev Setup ===');
        $this->info('');

        // ── 0. JWT Keys ───────────────────────────────────────────
        $this->generateJwtKeys();

        // ── 1. Platform Migrations ──────────────────────────────
        $this->info('[1/5] Rodando migrations da plataforma...');

        $migrateCommand = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

        Artisan::call($migrateCommand, [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);

        $this->info('  OK — Platform migrations executadas');

        // ── 2. Platform Seeds ───────────────────────────────────
        $this->info('[2/5] Populando dados da plataforma...');

        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\Platform\\PlatformSeeder',
            '--database' => 'platform',
            '--force' => true,
        ]);

        $this->info('  OK — Platform seeds executados');

        // ── 3. Criar Tenant DB ──────────────────────────────────
        $tenantDbName = 'tenant_condominio_solar';

        $this->info("[3/5] Criando banco do tenant ({$tenantDbName})...");

        if ($this->option('fresh') && $dbCreator->databaseExists($tenantDbName)) {
            $dbCreator->dropDatabase($tenantDbName);
            $this->info('  Banco anterior removido');
        }

        $dbCreator->createDatabase($tenantDbName);
        $this->info('  OK — Banco criado');

        // ── 4. Tenant Migrations ────────────────────────────────
        $this->info('[4/5] Rodando migrations do tenant...');

        $tenantManager->switchConnection($tenantDbName);

        $tenantMigrateCommand = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

        Artisan::call($tenantMigrateCommand, [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        $this->info('  OK — Tenant migrations executadas');

        // ── 5. Tenant Seeds ─────────────────────────────────────
        $this->info('[5/5] Populando dados do tenant...');

        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\Tenant\\TenantSeeder',
            '--database' => 'tenant',
            '--force' => true,
        ]);

        $tenantManager->resetConnection();

        $this->info('  OK — Tenant seeds executados');

        // ── Resumo ──────────────────────────────────────────────
        $this->info('');
        $this->info('=== Setup concluido com sucesso! ===');
        $this->info('');
        $this->table(
            ['Recurso', 'Valor'],
            [
                ['Platform DB', 'condominium_platform'],
                ['Tenant DB', $tenantDbName],
                ['Tenant Slug', 'condominio-solar'],
                ['', ''],
                ['Platform Admin', 'admin@plataforma.com.br / SenhaSegura@123'],
                ['Sindico', 'sindico@condominio.com.br / SenhaSegura123'],
                ['Morador', 'morador@email.com / SenhaSegura123'],
                ['', ''],
                ['Health', 'GET http://localhost:8000/tenant/health'],
                ['Login', 'POST http://localhost:8000/tenant/auth/login'],
            ],
        );
        $this->info('');

        return self::SUCCESS;
    }

    private function generateJwtKeys(): void
    {
        $keysPath = storage_path('keys');
        $privatePath = $keysPath.'/jwt-private.pem';
        $publicPath = $keysPath.'/jwt-public.pem';

        if (file_exists($privatePath) && file_exists($publicPath)) {
            return;
        }

        $this->info('[0/5] Gerando chaves JWT (RS256)...');

        if (! is_dir($keysPath)) {
            mkdir($keysPath, 0700, true);
        }

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($key, $privateKey);
        file_put_contents($privatePath, $privateKey);
        chmod($privatePath, 0600);

        $publicKey = openssl_pkey_get_details($key)['key'];
        file_put_contents($publicPath, $publicKey);
        chmod($publicPath, 0644);

        $this->info('  OK — Chaves JWT geradas em storage/keys/');
    }
}
