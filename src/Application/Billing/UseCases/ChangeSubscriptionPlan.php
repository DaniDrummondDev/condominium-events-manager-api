<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\PlanVersionRepositoryInterface;
use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use Application\Billing\DTOs\ChangeSubscriptionPlanDTO;
use Application\Billing\DTOs\SubscriptionDTO;
use Domain\Billing\Entities\Subscription;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ChangeSubscriptionPlan
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PlanVersionRepositoryInterface $planVersionRepository,
    ) {}

    public function execute(ChangeSubscriptionPlanDTO $dto): SubscriptionDTO
    {
        $subscriptionId = Uuid::fromString($dto->subscriptionId);
        $newPlanVersionId = Uuid::fromString($dto->newPlanVersionId);

        $subscription = $this->subscriptionRepository->findById($subscriptionId);

        if ($subscription === null) {
            throw new DomainException(
                'Subscription not found',
                'SUBSCRIPTION_NOT_FOUND',
                ['subscription_id' => $dto->subscriptionId],
            );
        }

        if ($subscription->planVersionId()->equals($newPlanVersionId)) {
            throw new DomainException(
                'New plan version is the same as current',
                'PLAN_VERSION_SAME',
                [
                    'subscription_id' => $dto->subscriptionId,
                    'plan_version_id' => $dto->newPlanVersionId,
                ],
            );
        }

        $newPlanVersion = $this->planVersionRepository->findById($newPlanVersionId);

        if ($newPlanVersion === null || ! $newPlanVersion->isActive()) {
            throw new DomainException(
                'New plan version not found or inactive',
                'PLAN_VERSION_NOT_AVAILABLE',
                ['plan_version_id' => $dto->newPlanVersionId],
            );
        }

        $subscription->changePlan($newPlanVersionId);

        $this->subscriptionRepository->save($subscription);

        return $this->toDTO($subscription);
    }

    private function toDTO(Subscription $subscription): SubscriptionDTO
    {
        return new SubscriptionDTO(
            id: $subscription->id()->value(),
            tenantId: $subscription->tenantId()->value(),
            planVersionId: $subscription->planVersionId()->value(),
            status: $subscription->status()->value,
            billingCycle: $subscription->billingCycle()->value,
            currentPeriodStart: $subscription->currentPeriod()->start()->format('c'),
            currentPeriodEnd: $subscription->currentPeriod()->end()->format('c'),
            gracePeriodEnd: $subscription->gracePeriodEnd()?->format('c'),
            canceledAt: $subscription->canceledAt()?->format('c'),
        );
    }
}
