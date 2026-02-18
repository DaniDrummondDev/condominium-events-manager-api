<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\PlanFeatureRepositoryInterface;
use Application\Billing\Contracts\PlanPriceRepositoryInterface;
use Application\Billing\Contracts\PlanRepositoryInterface;
use Application\Billing\Contracts\PlanVersionRepositoryInterface;
use Application\Billing\DTOs\CreatePlanDTO;
use Application\Billing\DTOs\PlanDTO;
use Application\Billing\DTOs\PlanPriceDTO;
use Application\Billing\DTOs\PlanVersionDTO;
use DateTimeImmutable;
use Domain\Billing\Entities\Plan;
use Domain\Billing\Entities\PlanFeature;
use Domain\Billing\Entities\PlanPrice;
use Domain\Billing\Entities\PlanVersion;
use Domain\Billing\Enums\BillingCycle;
use Domain\Billing\Enums\FeatureType;
use Domain\Billing\Enums\PlanStatus;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

final readonly class CreatePlan
{
    public function __construct(
        private PlanRepositoryInterface $planRepository,
        private PlanVersionRepositoryInterface $planVersionRepository,
        private PlanFeatureRepositoryInterface $planFeatureRepository,
        private PlanPriceRepositoryInterface $planPriceRepository,
    ) {}

    public function execute(CreatePlanDTO $dto): PlanDTO
    {
        $existing = $this->planRepository->findBySlug($dto->slug);

        if ($existing !== null) {
            throw new DomainException(
                "Plan with slug '{$dto->slug}' already exists",
                'PLAN_SLUG_DUPLICATE',
                ['slug' => $dto->slug],
            );
        }

        $plan = Plan::create(
            Uuid::generate(),
            $dto->name,
            $dto->slug,
        );

        $this->planRepository->save($plan);

        $planVersion = new PlanVersion(
            id: Uuid::generate(),
            planId: $plan->id(),
            version: 1,
            status: PlanStatus::Active,
            createdAt: new DateTimeImmutable,
        );

        $this->planVersionRepository->save($planVersion);

        $planPrices = [];
        foreach ($dto->prices as $priceData) {
            $planPrice = new PlanPrice(
                id: Uuid::generate(),
                planVersionId: $planVersion->id(),
                billingCycle: BillingCycle::from($priceData['billing_cycle']),
                price: new Money($priceData['price_in_cents'], $priceData['currency'] ?? 'BRL'),
                trialDays: $priceData['trial_days'] ?? 0,
            );
            $planPrices[] = $planPrice;
        }

        if ($planPrices !== []) {
            $this->planPriceRepository->saveMany($planPrices);
        }

        $features = [];
        foreach ($dto->features as $featureData) {
            $planFeature = new PlanFeature(
                id: Uuid::generate(),
                planVersionId: $planVersion->id(),
                featureKey: $featureData['feature_key'],
                value: $featureData['value'],
                type: FeatureType::from($featureData['type']),
            );
            $features[] = $planFeature;
        }

        if ($features !== []) {
            $this->planFeatureRepository->saveMany($features);
        }

        $priceDTOs = array_map(fn (PlanPrice $p) => new PlanPriceDTO(
            id: $p->id()->value(),
            billingCycle: $p->billingCycle()->value,
            priceInCents: $p->price()->amount(),
            currency: $p->price()->currency(),
            trialDays: $p->trialDays(),
        ), $planPrices);

        return new PlanDTO(
            id: $plan->id()->value(),
            name: $plan->name(),
            slug: $plan->slug(),
            status: $plan->status()->value,
            currentVersion: new PlanVersionDTO(
                id: $planVersion->id()->value(),
                version: $planVersion->version(),
                status: $planVersion->status()->value,
                createdAt: $planVersion->createdAt()->format('c'),
                prices: $priceDTOs,
            ),
            features: $dto->features,
        );
    }
}
