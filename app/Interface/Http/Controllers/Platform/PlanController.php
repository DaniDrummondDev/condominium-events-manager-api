<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Platform;

use App\Interface\Http\Requests\Platform\CreatePlanRequest;
use App\Interface\Http\Resources\Platform\PlanResource;
use Application\Billing\Contracts\PlanRepositoryInterface;
use Application\Billing\DTOs\CreatePlanDTO;
use Application\Billing\UseCases\CreatePlan;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanController
{
    public function index(PlanRepositoryInterface $planRepository): AnonymousResourceCollection
    {
        $plans = $planRepository->findAll();

        $dtos = array_map(fn ($plan) => new \Application\Billing\DTOs\PlanDTO(
            id: $plan->id()->value(),
            name: $plan->name(),
            slug: $plan->slug(),
            status: $plan->status()->value,
        ), $plans);

        return PlanResource::collection($dtos);
    }

    public function store(CreatePlanRequest $request, CreatePlan $useCase): JsonResponse
    {
        try {
            $features = [];
            foreach ($request->validated('features', []) as $feature) {
                $features[] = [
                    'feature_key' => $feature['key'],
                    'value' => $feature['value'],
                    'type' => $feature['type'],
                ];
            }

            $result = $useCase->execute(new CreatePlanDTO(
                name: $request->validated('name'),
                slug: $request->validated('slug'),
                priceInCents: $request->validated('price'),
                currency: $request->validated('currency', 'BRL'),
                billingCycle: $request->validated('billing_cycle'),
                trialDays: $request->validated('trial_days', 0),
                features: $features,
            ));

            return (new PlanResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $id, PlanRepositoryInterface $planRepository): JsonResponse
    {
        $plan = $planRepository->findById(Uuid::fromString($id));

        if ($plan === null) {
            return new JsonResponse(['error' => 'PLAN_NOT_FOUND', 'message' => 'Plan not found'], 404);
        }

        $dto = new \Application\Billing\DTOs\PlanDTO(
            id: $plan->id()->value(),
            name: $plan->name(),
            slug: $plan->slug(),
            status: $plan->status()->value,
        );

        return (new PlanResource($dto))->response();
    }
}
