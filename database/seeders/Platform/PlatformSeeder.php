<?php

declare(strict_types=1);

namespace Database\Seeders\Platform;

use App\Infrastructure\Persistence\Platform\Models\FeatureModel;
use App\Infrastructure\Persistence\Platform\Models\PlanFeatureModel;
use App\Infrastructure\Persistence\Platform\Models\PlanModel;
use App\Infrastructure\Persistence\Platform\Models\PlanVersionModel;
use App\Infrastructure\Persistence\Platform\Models\PlatformUserModel;
use App\Infrastructure\Persistence\Platform\Models\SubscriptionModel;
use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        // ── Platform Admin ──────────────────────────────────────
        PlatformUserModel::query()->updateOrCreate(
            ['email' => 'admin@plataforma.com.br'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Admin Plataforma',
                'password_hash' => Hash::make('SenhaSegura@123'),
                'role' => 'platform_owner',
                'status' => 'active',
                'mfa_enabled' => false,
            ],
        );

        // ── Plan "Basico" ───────────────────────────────────────
        $plan = PlanModel::query()->updateOrCreate(
            ['slug' => 'basico'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Básico',
                'status' => 'active',
            ],
        );

        $planVersion = PlanVersionModel::query()->updateOrCreate(
            ['plan_id' => $plan->id, 'version' => 1],
            [
                'id' => Str::uuid()->toString(),
                'price' => 299.90,
                'currency' => 'BRL',
                'billing_cycle' => 'monthly',
                'trial_days' => 15,
                'status' => 'active',
                'created_at' => now(),
            ],
        );

        // ── Features ────────────────────────────────────────────
        $features = [
            ['code' => 'max_units', 'name' => 'Máximo de Unidades', 'type' => 'limit', 'value' => '50'],
            ['code' => 'max_spaces', 'name' => 'Máximo de Espaços', 'type' => 'limit', 'value' => '10'],
            ['code' => 'max_users', 'name' => 'Máximo de Usuários', 'type' => 'limit', 'value' => '100'],
            ['code' => 'max_blocks', 'name' => 'Máximo de Blocos', 'type' => 'limit', 'value' => '10'],
        ];

        foreach ($features as $featureData) {
            $feature = FeatureModel::query()->updateOrCreate(
                ['code' => $featureData['code']],
                [
                    'id' => Str::uuid()->toString(),
                    'name' => $featureData['name'],
                    'type' => $featureData['type'],
                    'description' => null,
                ],
            );

            PlanFeatureModel::query()->updateOrCreate(
                ['plan_version_id' => $planVersion->id, 'feature_key' => $feature->code],
                [
                    'id' => Str::uuid()->toString(),
                    'value' => $featureData['value'],
                    'type' => 'integer',
                ],
            );
        }

        // ── Tenant "Condominio Solar" ───────────────────────────
        $tenant = TenantModel::query()->updateOrCreate(
            ['slug' => 'condominio-solar'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Condomínio Solar',
                'type' => 'vertical',
                'status' => 'active',
                'config' => [],
                'database_name' => 'tenant_condominio_solar',
                'provisioned_at' => now(),
            ],
        );

        // ── Subscription ────────────────────────────────────────
        SubscriptionModel::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'id' => Str::uuid()->toString(),
                'plan_version_id' => $planVersion->id,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->addYear(),
                'grace_period_end' => null,
                'canceled_at' => null,
            ],
        );

        $this->command->info('  Platform seeded: admin, plan, features, tenant, subscription');
    }
}
