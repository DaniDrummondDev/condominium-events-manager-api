<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListeners;

use App\Infrastructure\Cache\DashboardMetricsCache;
use App\Infrastructure\MultiTenancy\TenantContext;
use Domain\Reservation\Events\ReservationCanceled;
use Domain\Reservation\Events\ReservationConfirmed;
use Domain\Reservation\Events\ReservationRequested;

class InvalidateDashboardCache
{
    public function __construct(
        private readonly DashboardMetricsCache $cache,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(ReservationRequested|ReservationConfirmed|ReservationCanceled $event): void
    {
        $this->cache->invalidate($this->tenantContext->tenantId);
    }
}
