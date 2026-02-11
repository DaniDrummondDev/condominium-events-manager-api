<?php

declare(strict_types=1);

use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use Application\Billing\DTOs\CancelSubscriptionDTO;
use Application\Billing\DTOs\SubscriptionDTO;
use Application\Billing\UseCases\CancelSubscription;
use Domain\Billing\Entities\Subscription;
use Domain\Billing\Enums\BillingCycle;
use Domain\Billing\Enums\SubscriptionStatus;
use Domain\Billing\ValueObjects\BillingPeriod;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

test('cancels a subscription successfully with DTO', function () {
    $subscriptionId = Uuid::generate();
    $tenantId = Uuid::generate();
    $planVersionId = Uuid::generate();

    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-02-01');
    $period = new BillingPeriod($start, $end);

    $subscription = Subscription::create(
        $subscriptionId,
        $tenantId,
        $planVersionId,
        BillingCycle::Monthly,
        $period,
    );

    $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $repo->expects('findById')->andReturn($subscription);
    $repo->expects('save')->once();

    $useCase = new CancelSubscription($repo);

    $dto = new CancelSubscriptionDTO(
        subscriptionId: $subscriptionId->value(),
        cancellationType: 'end_of_period',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(SubscriptionDTO::class)
        ->and($result->id)->toBe($subscriptionId->value())
        ->and($result->status)->toBe(SubscriptionStatus::Canceled->value)
        ->and($result->canceledAt)->not()->toBeNull();
});

test('throws when subscription is not found', function () {
    $subscriptionId = Uuid::generate();

    $repo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $repo->expects('findById')->andReturnNull();

    $useCase = new CancelSubscription($repo);

    $dto = new CancelSubscriptionDTO(
        subscriptionId: $subscriptionId->value(),
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SUBSCRIPTION_NOT_FOUND')
            ->and($e->context())->toHaveKey('subscription_id', $subscriptionId->value());
    }
});
