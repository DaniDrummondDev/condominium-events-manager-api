<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Platform;

use App\Interface\Http\Requests\Platform\CreateFeatureOverrideRequest;
use Application\Billing\Contracts\TenantFeatureOverrideRepositoryInterface;
use Application\Billing\DTOs\CreateFeatureOverrideDTO;
use Application\Billing\UseCases\RemoveTenantFeatureOverride;
use Application\Billing\UseCases\SetTenantFeatureOverride;
use Domain\Auth\ValueObjects\TokenClaims;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;

class TenantFeatureOverrideController
{
    public function index(
        string $tenantId,
        TenantFeatureOverrideRepositoryInterface $overrideRepository,
    ): JsonResponse {
        $overrides = $overrideRepository->findByTenantId(Uuid::fromString($tenantId));

        $data = array_map(fn ($o) => [
            'id' => $o->id()->value(),
            'tenant_id' => $o->tenantId()->value(),
            'feature_id' => $o->featureId()->value(),
            'value' => $o->value(),
            'reason' => $o->reason(),
            'expires_at' => $o->expiresAt()?->format('c'),
            'created_at' => $o->createdAt()->format('c'),
        ], $overrides);

        return new JsonResponse(['data' => $data]);
    }

    public function store(
        string $tenantId,
        CreateFeatureOverrideRequest $request,
        SetTenantFeatureOverride $useCase,
    ): JsonResponse {
        try {
            /** @var TokenClaims $claims */
            $claims = app(TokenClaims::class);

            $override = $useCase->execute(new CreateFeatureOverrideDTO(
                tenantId: $tenantId,
                featureId: $request->validated('feature_id'),
                value: $request->validated('value'),
                reason: $request->validated('reason'),
                expiresAt: $request->validated('expires_at'),
                createdBy: $claims->sub->value(),
            ));

            return new JsonResponse([
                'data' => [
                    'id' => $override->id()->value(),
                    'tenant_id' => $override->tenantId()->value(),
                    'feature_id' => $override->featureId()->value(),
                    'value' => $override->value(),
                    'reason' => $override->reason(),
                    'expires_at' => $override->expiresAt()?->format('c'),
                    'created_at' => $override->createdAt()->format('c'),
                ],
            ], 201);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(
        string $tenantId,
        string $overrideId,
        RemoveTenantFeatureOverride $useCase,
    ): JsonResponse {
        try {
            $useCase->execute($tenantId, $overrideId);

            return new JsonResponse(null, 204);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
