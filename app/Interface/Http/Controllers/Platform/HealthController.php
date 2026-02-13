<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Platform;

use App\Infrastructure\Health\HealthCheckService;
use Illuminate\Http\JsonResponse;

class HealthController
{
    public function __construct(
        private readonly HealthCheckService $healthCheck,
    ) {}

    public function liveness(): JsonResponse
    {
        return response()->json($this->healthCheck->liveness());
    }

    public function readiness(): JsonResponse
    {
        $result = $this->healthCheck->readiness();
        $statusCode = $result['status'] === 'healthy' ? 200 : 503;

        return response()->json($result, $statusCode);
    }
}
