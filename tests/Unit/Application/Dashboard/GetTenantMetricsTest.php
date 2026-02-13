<?php

declare(strict_types=1);

use Application\Dashboard\Contracts\PlatformDashboardQueryInterface;
use Application\Dashboard\DTOs\TenantMetricsDTO;
use Application\Dashboard\UseCases\GetTenantMetrics;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

test('returns tenant metrics DTO', function () {
    $tenantId = Uuid::generate()->value();

    $expected = new TenantMetricsDTO(
        tenantId: $tenantId,
        unitsCount: 30,
        usersCount: 80,
        spacesCount: 4,
        reservationsThisMonth: 12,
        activeViolations: 3,
        measuredAt: '2026-02-13T10:00:00-03:00',
    );

    $query = Mockery::mock(PlatformDashboardQueryInterface::class);
    $query->shouldReceive('getTenantMetrics')
        ->once()
        ->with($tenantId)
        ->andReturn($expected);

    $useCase = new GetTenantMetrics($query);
    $result = $useCase->execute($tenantId);

    expect($result)->toBeInstanceOf(TenantMetricsDTO::class)
        ->and($result->tenantId)->toBe($tenantId)
        ->and($result->unitsCount)->toBe(30)
        ->and($result->usersCount)->toBe(80)
        ->and($result->spacesCount)->toBe(4)
        ->and($result->reservationsThisMonth)->toBe(12)
        ->and($result->activeViolations)->toBe(3);
});

test('throws when tenant not found', function () {
    $tenantId = Uuid::generate()->value();

    $query = Mockery::mock(PlatformDashboardQueryInterface::class);
    $query->shouldReceive('getTenantMetrics')
        ->once()
        ->with($tenantId)
        ->andReturnNull();

    $useCase = new GetTenantMetrics($query);
    $useCase->execute($tenantId);
})->throws(DomainException::class, 'Tenant not found');
