<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Platform;

use App\Interface\Http\Requests\Platform\CreatePlanRequest;
use App\Interface\Http\Resources\Platform\PlanResource;
use Application\Billing\Contracts\PlanFeatureRepositoryInterface;
use Application\Billing\Contracts\PlanPriceRepositoryInterface;
use Application\Billing\Contracts\PlanRepositoryInterface;
use Application\Billing\Contracts\PlanVersionRepositoryInterface;
use Application\Billing\DTOs\CreatePlanDTO;
use Application\Billing\DTOs\PlanDTO;
use Application\Billing\DTOs\PlanPriceDTO;
use Application\Billing\DTOs\PlanVersionDTO;
use Application\Billing\UseCases\CreatePlan;
use Domain\Billing\Entities\Plan;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanController
{
    public function index(
        PlanRepositoryInterface $planRepository,
        PlanVersionRepositoryInterface $planVersionRepository,
        PlanFeatureRepositoryInterface $planFeatureRepository,
        PlanPriceRepositoryInterface $planPriceRepository,
    ): AnonymousResourceCollection {
        $plans = $planRepository->findAll();

        $dtos = array_map(fn (Plan $plan) => $this->buildPlanDTO(
            $plan,
            $planVersionRepository,
            $planFeatureRepository,
            $planPriceRepository,
        ), $plans);

        return PlanResource::collection($dtos);
    }

    public function store(CreatePlanRequest $request, CreatePlan $useCase): JsonResponse
    {
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

            $result = $useCase->execute(new CreatePlanDTO(
                name: $request->validated('name'),
                slug: $request->validated('slug'),
                prices: $prices,
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

    public function show(
        string $id,
        PlanRepositoryInterface $planRepository,
        PlanVersionRepositoryInterface $planVersionRepository,
        PlanFeatureRepositoryInterface $planFeatureRepository,
        PlanPriceRepositoryInterface $planPriceRepository,
    ): JsonResponse {
        $plan = $planRepository->findById(Uuid::fromString($id));

        if ($plan === null) {
            return new JsonResponse(['error' => 'PLAN_NOT_FOUND', 'message' => 'Plan not found'], 404);
        }

        $dto = $this->buildPlanDTO($plan, $planVersionRepository, $planFeatureRepository, $planPriceRepository);

        return (new PlanResource($dto))->response();
    }

    private function buildPlanDTO(
        Plan $plan,
        PlanVersionRepositoryInterface $planVersionRepository,
        PlanFeatureRepositoryInterface $planFeatureRepository,
        PlanPriceRepositoryInterface $planPriceRepository,
    ): PlanDTO {
        $currentVersion = $planVersionRepository->findActiveByPlanId($plan->id());

        $versionDTO = null;
        $features = [];

        if ($currentVersion !== null) {
            $planPrices = $planPriceRepository->findByPlanVersionId($currentVersion->id());

            $priceDTOs = array_map(fn ($p) => new PlanPriceDTO(
                id: $p->id()->value(),
                billingCycle: $p->billingCycle()->value,
                priceInCents: $p->price()->amount(),
                currency: $p->price()->currency(),
                trialDays: $p->trialDays(),
            ), $planPrices);

            $versionDTO = new PlanVersionDTO(
                id: $currentVersion->id()->value(),
                version: $currentVersion->version(),
                status: $currentVersion->status()->value,
                createdAt: $currentVersion->createdAt()->format('c'),
                prices: $priceDTOs,
            );

            $planFeatures = $planFeatureRepository->findByPlanVersionId($currentVersion->id());
            $features = array_map(fn ($f) => [
                'feature_key' => $f->featureKey(),
                'value' => $f->value(),
                'type' => $f->type()->value,
            ], $planFeatures);
        }

        return new PlanDTO(
            id: $plan->id()->value(),
            name: $plan->name(),
            slug: $plan->slug(),
            status: $plan->status()->value,
            currentVersion: $versionDTO,
            features: $features,
        );
    }
}
