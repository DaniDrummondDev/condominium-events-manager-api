<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\PlanFeatureRepositoryInterface;
use Application\Billing\Contracts\PlanRepositoryInterface;
use Application\Billing\Contracts\PlanVersionRepositoryInterface;
use Application\Billing\DTOs\CreatePlanVersionDTO;
use Application\Billing\DTOs\PlanVersionDTO;
use DateTimeImmutable;
use Domain\Billing\Entities\PlanFeature;
use Domain\Billing\Entities\PlanVersion;
use Domain\Billing\Enums\BillingCycle;
use Domain\Billing\Enums\FeatureType;
use Domain\Billing\Enums\PlanStatus;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

final readonly class CreatePlanVersion
{
    public function __construct(
        private PlanRepositoryInterface $planRepository,
        private PlanVersionRepositoryInterface $planVersionRepository,
        private PlanFeatureRepositoryInterface $planFeatureRepository,
    ) {}

    public function execute(CreatePlanVersionDTO $dto): PlanVersionDTO
    {
        $planId = Uuid::fromString($dto->planId);
        $plan = $this->planRepository->findById($planId);

        if ($plan === null) {
            throw new DomainException(
                'Plan not found',
                'PLAN_NOT_FOUND',
                ['plan_id' => $dto->planId],
            );
        }

        $currentVersion = $this->planVersionRepository->findActiveByPlanId($planId);

        if ($currentVersion !== null) {
            $currentVersion->deactivate();
            $this->planVersionRepository->save($currentVersion);
        }

        $nextVersion = $this->planVersionRepository->nextVersionNumber($planId);

        $planVersion = new PlanVersion(
            id: Uuid::generate(),
            planId: $planId,
            version: $nextVersion,
            price: new Money($dto->priceInCents, $dto->currency),
            billingCycle: BillingCycle::from($dto->billingCycle),
            trialDays: $dto->trialDays,
            status: PlanStatus::Active,
            createdAt: new DateTimeImmutable,
        );

        $this->planVersionRepository->save($planVersion);

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

        return new PlanVersionDTO(
            id: $planVersion->id()->value(),
            version: $planVersion->version(),
            priceInCents: $planVersion->price()->amount(),
            currency: $planVersion->price()->currency(),
            billingCycle: $planVersion->billingCycle()->value,
            trialDays: $planVersion->trialDays(),
            status: $planVersion->status()->value,
            createdAt: $planVersion->createdAt()->format('c'),
        );
    }
}
