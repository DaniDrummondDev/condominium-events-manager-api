<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Platform;

use App\Interface\Http\Resources\Platform\PublicPlanResource;
use Application\Billing\Contracts\PlanFeatureRepositoryInterface;
use Application\Billing\Contracts\PlanPriceRepositoryInterface;
use Application\Billing\Contracts\PlanRepositoryInterface;
use Application\Billing\Contracts\PlanVersionRepositoryInterface;
use Application\Billing\DTOs\PlanDTO;
use Application\Billing\DTOs\PlanPriceDTO;
use Application\Billing\DTOs\PlanVersionDTO;
use Domain\Billing\Entities\Plan;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicPlanController
{
    public function index(
        PlanRepositoryInterface $planRepository,
        PlanVersionRepositoryInterface $planVersionRepository,
        PlanFeatureRepositoryInterface $planFeatureRepository,
        PlanPriceRepositoryInterface $planPriceRepository,
    ): AnonymousResourceCollection {
        $plans = $planRepository->findAllActive();

        $dtos = array_map(fn (Plan $plan) => $this->buildPlanDTO(
            $plan,
            $planVersionRepository,
            $planFeatureRepository,
            $planPriceRepository,
        ), $plans);

        return PublicPlanResource::collection($dtos);
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
