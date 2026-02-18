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
            $prices = [];
            foreach ($request->validated('prices', []) as $price) {
                $prices[] = [
                    'billing_cycle' => $price['billing_cycle'],
                    'price_in_cents' => $price['price'],
                    'currency' => $price['currency'] ?? 'BRL',
                    'trial_days' => $price['trial_days'] ?? 0,
                ];
            }

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
                prices: $prices,
                features: $features,
            ));

            return new JsonResponse([
                'data' => [
                    'id' => $result->id,
                    'version' => $result->version,
                    'status' => $result->status,
                    'created_at' => $result->createdAt,
                    'prices' => array_map(fn ($p) => [
                        'id' => $p->id,
                        'billing_cycle' => $p->billingCycle,
                        'price_in_cents' => $p->priceInCents,
                        'currency' => $p->currency,
                        'trial_days' => $p->trialDays,
                    ], $result->prices),
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
