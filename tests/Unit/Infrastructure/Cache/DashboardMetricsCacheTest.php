<?php

declare(strict_types=1);

use App\Infrastructure\Cache\DashboardMetricsCache;
use Illuminate\Support\Facades\Cache;

test('putOverview stores and getOverview retrieves overview metrics', function (): void {
    $cache = new DashboardMetricsCache;
    $tenantId = 'tenant-123';
    $metrics = [
        'total_reservations' => 45,
        'active_units' => 120,
        'pending_approvals' => 3,
    ];

    $cache->putOverview($tenantId, $metrics);
    $retrieved = $cache->getOverview($tenantId);

    expect($retrieved)->toBe($metrics);
});

test('getOverview returns null when data does not exist', function (): void {
    $cache = new DashboardMetricsCache;

    $result = $cache->getOverview('nonexistent-tenant');

    expect($result)->toBeNull();
});

test('putResident stores and getResident retrieves resident metrics', function (): void {
    $cache = new DashboardMetricsCache;
    $tenantId = 'tenant-456';
    $residentId = 'resident-789';
    $metrics = [
        'total_reservations' => 12,
        'active_reservations' => 2,
        'infractions_count' => 0,
    ];

    $cache->putResident($tenantId, $residentId, $metrics);
    $retrieved = $cache->getResident($tenantId, $residentId);

    expect($retrieved)->toBe($metrics);
});

test('getResident returns null when data does not exist', function (): void {
    $cache = new DashboardMetricsCache;

    $result = $cache->getResident('tenant-999', 'resident-999');

    expect($result)->toBeNull();
});

test('invalidate forgets overview cache for tenant', function (): void {
    $cache = new DashboardMetricsCache;
    $tenantId = 'tenant-clear';
    $metrics = ['total_reservations' => 100];

    $cache->putOverview($tenantId, $metrics);
    expect($cache->getOverview($tenantId))->toBe($metrics);

    $cache->invalidate($tenantId);

    expect($cache->getOverview($tenantId))->toBeNull();
});

test('invalidate does not affect resident cache', function (): void {
    $cache = new DashboardMetricsCache;
    $tenantId = 'tenant-partial';
    $residentId = 'resident-partial';
    $overviewMetrics = ['total_reservations' => 50];
    $residentMetrics = ['total_reservations' => 5];

    $cache->putOverview($tenantId, $overviewMetrics);
    $cache->putResident($tenantId, $residentId, $residentMetrics);

    $cache->invalidate($tenantId);

    expect($cache->getOverview($tenantId))->toBeNull()
        ->and($cache->getResident($tenantId, $residentId))->toBe($residentMetrics);
});

test('cache uses correct key format for overview', function (): void {
    Cache::spy();

    $cache = new DashboardMetricsCache;
    $tenantId = 'tenant-key-test';
    $metrics = ['value' => 42];

    $cache->putOverview($tenantId, $metrics);

    Cache::shouldHaveReceived('put')
        ->once()
        ->with('dashboard:overview:tenant-key-test', $metrics, 60);
});

test('cache uses correct key format for resident', function (): void {
    Cache::spy();

    $cache = new DashboardMetricsCache;
    $tenantId = 'tenant-key-test';
    $residentId = 'resident-key-test';
    $metrics = ['value' => 24];

    $cache->putResident($tenantId, $residentId, $metrics);

    Cache::shouldHaveReceived('put')
        ->once()
        ->with('dashboard:resident:tenant-key-test:resident-key-test', $metrics, 60);
});
