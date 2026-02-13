<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Infrastructure\Auth\AuthenticatedUser;
use Application\Dashboard\UseCases\GetResidentDashboard;
use Application\Dashboard\UseCases\GetTenantDashboard;
use Illuminate\Http\JsonResponse;

class DashboardController
{
    public function overview(GetTenantDashboard $useCase): JsonResponse
    {
        $result = $useCase->execute();

        return new JsonResponse(['data' => $result]);
    }

    public function resident(
        GetResidentDashboard $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        $result = $useCase->execute($user->userId->value());

        return new JsonResponse(['data' => $result]);
    }
}
