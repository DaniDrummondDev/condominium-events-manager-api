<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use Application\Billing\DTOs\SubscriptionDTO;
use Domain\Billing\Entities\Subscription;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class RenewSubscription
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
    ) {}

    public function execute(string $subscriptionId): SubscriptionDTO
    {
        $id = Uuid::fromString($subscriptionId);
        $subscription = $this->subscriptionRepository->findById($id);

        if ($subscription === null) {
            throw new DomainException(
                'Subscription not found',
                'SUBSCRIPTION_NOT_FOUND',
                ['subscription_id' => $subscriptionId],
            );
        }

        $newPeriod = $subscription->currentPeriod()->next($subscription->billingCycle());

        $subscription->renew($newPeriod);

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
