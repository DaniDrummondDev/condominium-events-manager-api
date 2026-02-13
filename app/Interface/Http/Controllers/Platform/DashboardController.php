<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Platform;

use Application\Dashboard\UseCases\GetPlatformDashboard;
use Application\Dashboard\UseCases\GetTenantMetrics;
use Domain\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;

class DashboardController
{
    public function index(GetPlatformDashboard $useCase): JsonResponse
    {
        $result = $useCase->execute();

        return new JsonResponse(['data' => $result]);
    }

    public function tenantMetrics(
        string $id,
        GetTenantMetrics $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id);

            return new JsonResponse(['data' => $result]);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
