<?php

declare(strict_types=1);

use App\Infrastructure\Cache\DashboardMetricsCache;
use App\Infrastructure\Cache\SpaceAvailabilityCache;
use App\Infrastructure\EventListeners\InvalidateDashboardCache;
use App\Infrastructure\EventListeners\InvalidateSpaceAvailabilityCache;
use App\Infrastructure\MultiTenancy\TenantContext;
use Domain\Reservation\Events\ReservationCanceled;
use Domain\Reservation\Events\ReservationConfirmed;
use Domain\Reservation\Events\ReservationRequested;

test('InvalidateSpaceAvailabilityCache calls invalidateRange with ReservationRequested event', function (): void {
    $spaceAvailabilityCacheMock = Mockery::mock(SpaceAvailabilityCache::class);
    $listener = new InvalidateSpaceAvailabilityCache($spaceAvailabilityCacheMock);

    $event = new ReservationRequested(
        reservationId: 'res-123',
        spaceId: 'space-456',
        unitId: 'unit-789',
        residentId: 'resident-abc',
        startDatetime: '2026-02-20 10:00:00',
        endDatetime: '2026-02-22 12:00:00',
    );

    $spaceAvailabilityCacheMock
        ->shouldReceive('invalidateRange')
        ->once()
        ->with('space-456', '2026-02-20 10:00:00', '2026-02-22 12:00:00');

    $listener->handle($event);
});

test('InvalidateSpaceAvailabilityCache handles multiple reservation requests', function (): void {
    $spaceAvailabilityCacheMock = Mockery::mock(SpaceAvailabilityCache::class);
    $listener = new InvalidateSpaceAvailabilityCache($spaceAvailabilityCacheMock);

    $event1 = new ReservationRequested(
        reservationId: 'res-001',
        spaceId: 'space-pool',
        unitId: 'unit-101',
        residentId: 'resident-001',
        startDatetime: '2026-03-01 14:00:00',
        endDatetime: '2026-03-01 16:00:00',
    );

    $event2 = new ReservationRequested(
        reservationId: 'res-002',
        spaceId: 'space-gym',
        unitId: 'unit-202',
        residentId: 'resident-002',
        startDatetime: '2026-04-10 09:00:00',
        endDatetime: '2026-04-12 11:00:00',
    );

    $spaceAvailabilityCacheMock
        ->shouldReceive('invalidateRange')
        ->once()
        ->with('space-pool', '2026-03-01 14:00:00', '2026-03-01 16:00:00');

    $spaceAvailabilityCacheMock
        ->shouldReceive('invalidateRange')
        ->once()
        ->with('space-gym', '2026-04-10 09:00:00', '2026-04-12 11:00:00');

    $listener->handle($event1);
    $listener->handle($event2);
});

test('InvalidateDashboardCache calls invalidate with tenant ID from context', function (): void {
    $dashboardMetricsCacheMock = Mockery::mock(DashboardMetricsCache::class);
    $tenantContext = new TenantContext(
        tenantId: 'tenant-123',
        tenantSlug: 'condo-alpha',
        tenantName: 'Condominium Alpha',
        tenantType: 'vertical',
        tenantStatus: 'active',
        databaseName: 'tenant_condo_alpha',
        resolvedAt: new DateTimeImmutable,
    );
    $listener = new InvalidateDashboardCache($dashboardMetricsCacheMock, $tenantContext);

    $event = new ReservationRequested(
        reservationId: 'res-dashboard',
        spaceId: 'space-dashboard',
        unitId: 'unit-dashboard',
        residentId: 'resident-dashboard',
        startDatetime: '2026-05-01 08:00:00',
        endDatetime: '2026-05-01 10:00:00',
    );

    $dashboardMetricsCacheMock
        ->shouldReceive('invalidate')
        ->once()
        ->with('tenant-123');

    $listener->handle($event);
});

test('InvalidateDashboardCache handles ReservationConfirmed event', function (): void {
    $dashboardMetricsCacheMock = Mockery::mock(DashboardMetricsCache::class);
    $tenantContext = new TenantContext(
        tenantId: 'tenant-456',
        tenantSlug: 'condo-beta',
        tenantName: 'Condominium Beta',
        tenantType: 'horizontal',
        tenantStatus: 'active',
        databaseName: 'tenant_condo_beta',
        resolvedAt: new DateTimeImmutable,
    );
    $listener = new InvalidateDashboardCache($dashboardMetricsCacheMock, $tenantContext);

    $event = new ReservationConfirmed(
        reservationId: 'res-confirmed-dash',
        spaceId: 'space-confirmed-dash',
        unitId: 'unit-confirmed-dash',
        residentId: 'resident-confirmed-dash',
        approvedBy: 'sindico-123',
    );

    $dashboardMetricsCacheMock
        ->shouldReceive('invalidate')
        ->once()
        ->with('tenant-456');

    $listener->handle($event);
});

test('InvalidateDashboardCache handles ReservationCanceled event', function (): void {
    $dashboardMetricsCacheMock = Mockery::mock(DashboardMetricsCache::class);
    $tenantContext = new TenantContext(
        tenantId: 'tenant-789',
        tenantSlug: 'condo-gamma',
        tenantName: 'Condominium Gamma',
        tenantType: 'mixed',
        tenantStatus: 'active',
        databaseName: 'tenant_condo_gamma',
        resolvedAt: new DateTimeImmutable,
    );
    $listener = new InvalidateDashboardCache($dashboardMetricsCacheMock, $tenantContext);

    $event = new ReservationCanceled(
        reservationId: 'res-canceled-dash',
        spaceId: 'space-canceled-dash',
        residentId: 'resident-canceled-dash',
        canceledBy: 'resident-canceled-dash',
        cancellationReason: 'Emergency',
        isLateCancellation: false,
    );

    $dashboardMetricsCacheMock
        ->shouldReceive('invalidate')
        ->once()
        ->with('tenant-789');

    $listener->handle($event);
});
