<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Platform;

use App\Interface\Http\Requests\Platform\RegisterTenantRequest;
use Application\Tenant\DTOs\RegisterTenantDTO;
use Application\Tenant\UseCases\RegisterTenant;
use Domain\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;

class RegisterTenantController
{
    public function __invoke(
        RegisterTenantRequest $request,
        RegisterTenant $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new RegisterTenantDTO(
                slug: $request->validated('condominium.slug'),
                name: $request->validated('condominium.name'),
                type: $request->validated('condominium.type'),
                adminName: $request->validated('admin.name'),
                adminEmail: $request->validated('admin.email'),
                adminPassword: $request->validated('admin.password'),
                adminPhone: $request->validated('admin.phone'),
                planSlug: $request->validated('plan_slug'),
            ));

            return new JsonResponse([
                'data' => [
                    'slug' => $result->slug,
                    'message' => 'Verifique seu email para continuar o cadastro.',
                ],
            ], 202);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
