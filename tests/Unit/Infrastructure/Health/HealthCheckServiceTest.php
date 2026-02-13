<?php

declare(strict_types=1);

use App\Infrastructure\Health\HealthCheckService;
use Illuminate\Support\Facades\DB;

test('liveness returns healthy status with timestamp', function (): void {
    $service = new HealthCheckService;

    $result = $service->liveness();

    expect($result)
        ->toHaveKey('status', 'healthy')
        ->toHaveKey('timestamp')
        ->and($result['timestamp'])->toBeString();
});

test('readiness returns healthy when all checks pass', function (): void {
    $service = new HealthCheckService;

    $result = $service->readiness();

    expect($result)
        ->toHaveKey('status', 'healthy')
        ->toHaveKey('timestamp')
        ->toHaveKey('checks')
        ->and($result['checks'])
        ->toHaveKeys(['database', 'cache', 'queue'])
        ->and($result['checks']['database']['status'])->toBe('ok')
        ->and($result['checks']['cache']['status'])->toBe('ok')
        ->and($result['checks']['queue']['status'])->toBe('ok');
});

test('readiness returns degraded when database fails', function (): void {
    DB::shouldReceive('select')
        ->with('SELECT 1')
        ->once()
        ->andThrow(new \Exception('Connection refused'));

    $service = new HealthCheckService;

    $result = $service->readiness();

    expect($result)
        ->toHaveKey('status', 'degraded')
        ->toHaveKey('checks')
        ->and($result['checks']['database']['status'])->toBe('error')
        ->and($result['checks']['database']['error'])->toBe('Database connection failed');
});

test('readiness includes latency metrics for successful checks', function (): void {
    $service = new HealthCheckService;

    $result = $service->readiness();

    expect($result['checks']['database'])
        ->toHaveKey('latency_ms')
        ->and($result['checks']['database']['latency_ms'])->toBeFloat()
        ->and($result['checks']['cache'])
        ->toHaveKey('latency_ms')
        ->and($result['checks']['cache']['latency_ms'])->toBeFloat()
        ->and($result['checks']['queue'])
        ->toHaveKey('latency_ms')
        ->and($result['checks']['queue']['latency_ms'])->toBeFloat();
});
