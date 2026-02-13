<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckService
{
    /**
     * @return array{status: string, timestamp: string}
     */
    public function liveness(): array
    {
        return [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{status: string, timestamp: string, checks: array<string, array<string, mixed>>}
     */
    public function readiness(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $allHealthy = collect($checks)->every(fn (array $check): bool => $check['status'] === 'ok');

        return [
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /**
     * @return array{status: string, latency_ms?: float, error?: string}
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = (microtime(true) - $start) * 1000;

            return [
                'status' => 'ok',
                'latency_ms' => round($latency, 2),
            ];
        } catch (\Throwable) {
            return [
                'status' => 'error',
                'error' => 'Database connection failed',
            ];
        }
    }

    /**
     * @return array{status: string, latency_ms?: float, error?: string}
     */
    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health_check_' . time();
            Cache::put($key, 'ok', 10);
            Cache::get($key);
            Cache::forget($key);
            $latency = (microtime(true) - $start) * 1000;

            return [
                'status' => 'ok',
                'latency_ms' => round($latency, 2),
            ];
        } catch (\Throwable) {
            return [
                'status' => 'error',
                'error' => 'Cache connection failed',
            ];
        }
    }

    /**
     * @return array{status: string, latency_ms?: float, error?: string}
     */
    private function checkQueue(): array
    {
        try {
            $start = microtime(true);

            $driver = config('queue.default');

            match ($driver) {
                'redis' => Redis::connection('default')->ping(),
                'sync' => true,
                default => DB::table(config('queue.connections.database.table', 'jobs'))->count(),
            };

            $latency = (microtime(true) - $start) * 1000;

            return [
                'status' => 'ok',
                'latency_ms' => round($latency, 2),
            ];
        } catch (\Throwable) {
            return [
                'status' => 'error',
                'error' => 'Queue connection failed',
            ];
        }
    }
}
