<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\PlanVersionRepositoryInterface;
use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use Application\Billing\DTOs\CreateSubscriptionDTO;
use Application\Billing\DTOs\SubscriptionDTO;
use DateTimeImmutable;
use Domain\Billing\Entities\Subscription;
use Domain\Billing\Enums\BillingCycle;
use Domain\Billing\ValueObjects\BillingPeriod;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class CreateSubscription
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PlanVersionRepositoryInterface $planVersionRepository,
    ) {}

    public function execute(CreateSubscriptionDTO $dto): SubscriptionDTO
    {
        $tenantId = Uuid::fromString($dto->tenantId);
        $planVersionId = Uuid::fromString($dto->planVersionId);

        $existing = $this->subscriptionRepository->findActiveByTenantId($tenantId);

        if ($existing !== null) {
            return $this->toDTO($existing);
        }

        $planVersion = $this->planVersionRepository->findById($planVersionId);

        if ($planVersion === null || ! $planVersion->isActive()) {
            throw new DomainException(
                'Plan version not found or inactive',
                'PLAN_VERSION_NOT_AVAILABLE',
                ['plan_version_id' => $dto->planVersionId],
            );
        }

        $billingCycle = BillingCycle::from($dto->billingCycle);
        $startDate = $dto->startDate !== null
            ? new DateTimeImmutable($dto->startDate)
            : new DateTimeImmutable;

        $modifier = match ($billingCycle) {
            BillingCycle::Monthly => '+1 month',
            BillingCycle::Yearly => '+1 year',
        };

        $period = new BillingPeriod($startDate, $startDate->modify($modifier));

        if ($planVersion->hasTrialPeriod()) {
            $trialEnd = $startDate->modify("+{$planVersion->trialDays()} days");
            $trialPeriod = new BillingPeriod($startDate, $trialEnd);

            $subscription = Subscription::createTrialing(
                Uuid::generate(),
                $tenantId,
                $planVersionId,
                $billingCycle,
                $trialPeriod,
            );
        } else {
            $subscription = Subscription::create(
                Uuid::generate(),
                $tenantId,
                $planVersionId,
                $billingCycle,
                $period,
            );
        }

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
