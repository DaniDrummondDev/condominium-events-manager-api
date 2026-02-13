<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Queries;

use App\Infrastructure\Persistence\Platform\Models\InvoiceModel;
use App\Infrastructure\Persistence\Platform\Models\SubscriptionModel;
use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use App\Infrastructure\Persistence\Tenant\Models\ReservationModel;
use App\Infrastructure\Persistence\Tenant\Models\SpaceModel;
use App\Infrastructure\Persistence\Tenant\Models\TenantUserModel;
use App\Infrastructure\Persistence\Tenant\Models\UnitModel;
use App\Infrastructure\Persistence\Tenant\Models\ViolationModel;
use Application\Dashboard\Contracts\PlatformDashboardQueryInterface;
use Application\Dashboard\DTOs\PlatformDashboardDTO;
use Application\Dashboard\DTOs\TenantMetricsDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class EloquentPlatformDashboardQuery implements PlatformDashboardQueryInterface
{
    public function getPlatformDashboard(): PlatformDashboardDTO
    {
        $now = Carbon::now();

        $totalActiveTenants = TenantModel::where('status', 'active')->count();

        $newTenantsThisMonth = TenantModel::whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $totalSubscriptions = SubscriptionModel::count();

        $subscriptionsByStatus = SubscriptionModel::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $trialSubscriptions = SubscriptionModel::where('status', 'trial')->count();
        $pastDueSubscriptions = SubscriptionModel::where('status', 'past_due')->count();

        $mrrCents = (int) InvoiceModel::where('status', 'paid')
            ->whereYear('paid_at', $now->year)
            ->whereMonth('paid_at', $now->month)
            ->sum('total');

        return new PlatformDashboardDTO(
            totalActiveTenants: $totalActiveTenants,
            newTenantsThisMonth: $newTenantsThisMonth,
            totalSubscriptions: $totalSubscriptions,
            subscriptionsByStatus: $subscriptionsByStatus,
            trialSubscriptions: $trialSubscriptions,
            pastDueSubscriptions: $pastDueSubscriptions,
            mrrCents: $mrrCents,
            totalReservationsPlatform: null,
        );
    }

    public function getTenantMetrics(string $tenantId): ?TenantMetricsDTO
    {
        $tenant = TenantModel::find($tenantId);

        if ($tenant === null) {
            return null;
        }

        $databaseName = $tenant->database_name ?? 'tenant_'.$tenant->slug;
        $previousDatabase = Config::get('database.connections.tenant.database');

        try {
            Config::set('database.connections.tenant.database', $databaseName);
            DB::purge('tenant');
            DB::reconnect('tenant');

            $now = Carbon::now();

            $unitsCount = UnitModel::where('status', 'active')->count();
            $usersCount = TenantUserModel::where('status', 'active')->count();
            $spacesCount = SpaceModel::where('status', 'active')->count();

            $reservationsThisMonth = ReservationModel::whereYear('created_at', $now->year)
                ->whereMonth('created_at', $now->month)
                ->count();

            $activeViolations = ViolationModel::whereIn('status', ['registered', 'under_review'])->count();

            return new TenantMetricsDTO(
                tenantId: $tenantId,
                unitsCount: $unitsCount,
                usersCount: $usersCount,
                spacesCount: $spacesCount,
                reservationsThisMonth: $reservationsThisMonth,
                activeViolations: $activeViolations,
                measuredAt: $now->toIso8601String(),
            );
        } finally {
            Config::set('database.connections.tenant.database', $previousDatabase);
            DB::purge('tenant');

            if ($previousDatabase !== null) {
                DB::reconnect('tenant');
            }
        }
    }
}
