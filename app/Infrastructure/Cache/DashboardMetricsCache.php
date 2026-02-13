<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Illuminate\Support\Facades\Cache;

class DashboardMetricsCache
{
    private const int CACHE_TTL_SECONDS = 60;

    /**
     * @return array<string, mixed>|null
     */
    public function getOverview(string $tenantId): ?array
    {
        return Cache::get("dashboard:overview:{$tenantId}");
    }

    /**
     * @param array<string, mixed> $metrics
     */
    public function putOverview(string $tenantId, array $metrics): void
    {
        Cache::put("dashboard:overview:{$tenantId}", $metrics, self::CACHE_TTL_SECONDS);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResident(string $tenantId, string $residentId): ?array
    {
        return Cache::get("dashboard:resident:{$tenantId}:{$residentId}");
    }

    /**
     * @param array<string, mixed> $metrics
     */
    public function putResident(string $tenantId, string $residentId, array $metrics): void
    {
        Cache::put("dashboard:resident:{$tenantId}:{$residentId}", $metrics, self::CACHE_TTL_SECONDS);
    }

    public function invalidate(string $tenantId): void
    {
        Cache::forget("dashboard:overview:{$tenantId}");
    }
}
