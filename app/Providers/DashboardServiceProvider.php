<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Persistence\Platform\Queries\EloquentPlatformDashboardQuery;
use App\Infrastructure\Persistence\Tenant\Queries\EloquentTenantDashboardQuery;
use Application\Dashboard\Contracts\PlatformDashboardQueryInterface;
use Application\Dashboard\Contracts\TenantDashboardQueryInterface;
use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantDashboardQueryInterface::class, EloquentTenantDashboardQuery::class);
        $this->app->bind(PlatformDashboardQueryInterface::class, EloquentPlatformDashboardQuery::class);
    }
}
