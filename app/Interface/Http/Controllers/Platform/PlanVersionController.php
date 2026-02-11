<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Platform;

use App\Interface\Http\Requests\Platform\CreatePlanVersionRequest;
use Application\Billing\DTOs\CreatePlanVersionDTO;
use Application\Billing\UseCases\CreatePlanVersion;
use Domain\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;

class PlanVersionController
{
    public function store(
        string $planId,
        CreatePlanVersionRequest $request,
        CreatePlanVersion $useCase,
    ): JsonResponse {
        try {
            $features = [];
            foreach ($request->validated('features', []) as $feature) {
                $features[] = [
                    'feature_key' => $feature['key'],
                    'value' => $feature['value'],
                    'type' => $feature['type'],
                ];
            }

            $result = $useCase->execute(new CreatePlanVersionDTO(
                planId: $planId,
                priceInCents: $request->validated('price'),
                currency: $request->validated('currency', 'BRL'),
                billingCycle: $request->validated('billing_cycle'),
                trialDays: $request->validated('trial_days', 0),
                features: $features,
            ));

            return new JsonResponse([
                'data' => [
                    'id' => $result->id,
                    'version' => $result->version,
                    'price_in_cents' => $result->priceInCents,
                    'currency' => $result->currency,
                    'billing_cycle' => $result->billingCycle,
                    'trial_days' => $result->trialDays,
                    'status' => $result->status,
                    'created_at' => $result->createdAt,
                ],
            ], 201);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
