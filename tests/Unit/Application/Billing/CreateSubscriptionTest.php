<?php

declare(strict_types=1);

use Application\Billing\Contracts\PlanPriceRepositoryInterface;
use Application\Billing\Contracts\PlanVersionRepositoryInterface;
use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use Application\Billing\DTOs\CreateSubscriptionDTO;
use Application\Billing\DTOs\SubscriptionDTO;
use Application\Billing\UseCases\CreateSubscription;
use Domain\Billing\Entities\PlanPrice;
use Domain\Billing\Entities\PlanVersion;
use Domain\Billing\Entities\Subscription;
use Domain\Billing\Enums\BillingCycle;
use Domain\Billing\Enums\PlanStatus;
use Domain\Billing\Enums\SubscriptionStatus;
use Domain\Billing\ValueObjects\BillingPeriod;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

function createActivePlanVersion(
    ?Uuid $id = null,
): PlanVersion {
    return new PlanVersion(
        id: $id ?? Uuid::generate(),
        planId: Uuid::generate(),
        version: 1,
        status: PlanStatus::Active,
        createdAt: new DateTimeImmutable,
    );
}

function createActivePlanPrice(
    Uuid $planVersionId,
    BillingCycle $billingCycle = BillingCycle::Monthly,
    int $trialDays = 0,
): PlanPrice {
    return new PlanPrice(
        id: Uuid::generate(),
        planVersionId: $planVersionId,
        billingCycle: $billingCycle,
        price: new Money(9900, 'BRL'),
        trialDays: $trialDays,
    );
}

function createExistingSubscription(
    Uuid $tenantId,
    Uuid $planVersionId,
): Subscription {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-02-01');
    $period = new BillingPeriod($start, $end);

    return Subscription::create(
        Uuid::generate(),
        $tenantId,
        $planVersionId,
        BillingCycle::Monthly,
        $period,
    );
}

test('creates a new subscription successfully', function () {
    $tenantId = Uuid::generate();
    $planVersionId = Uuid::generate();
    $planVersion = createActivePlanVersion($planVersionId);
    $planPrice = createActivePlanPrice($planVersionId);

    $subscriptionRepo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $subscriptionRepo->expects('findActiveByTenantId')->andReturnNull();
    $subscriptionRepo->expects('save')->once();

    $planVersionRepo = Mockery::mock(PlanVersionRepositoryInterface::class);
    $planVersionRepo->expects('findById')->andReturn($planVersion);

    $planPriceRepo = Mockery::mock(PlanPriceRepositoryInterface::class);
    $planPriceRepo->expects('findByPlanVersionIdAndBillingCycle')->andReturn($planPrice);

    $useCase = new CreateSubscription($subscriptionRepo, $planVersionRepo, $planPriceRepo);

    $dto = new CreateSubscriptionDTO(
        tenantId: $tenantId->value(),
        planVersionId: $planVersionId->value(),
        billingCycle: 'monthly',
        startDate: '2025-03-01',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(SubscriptionDTO::class)
        ->and($result->tenantId)->toBe($tenantId->value())
        ->and($result->planVersionId)->toBe($planVersionId->value())
        ->and($result->status)->toBe(SubscriptionStatus::Active->value)
        ->and($result->billingCycle)->toBe(BillingCycle::Monthly->value);
});

test('returns existing subscription when tenant already has one (idempotent)', function () {
    $tenantId = Uuid::generate();
    $planVersionId = Uuid::generate();

    $existing = createExistingSubscription($tenantId, $planVersionId);

    $subscriptionRepo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $subscriptionRepo->expects('findActiveByTenantId')->andReturn($existing);
    $subscriptionRepo->shouldNotReceive('save');

    $planVersionRepo = Mockery::mock(PlanVersionRepositoryInterface::class);
    $planVersionRepo->shouldNotReceive('findById');

    $planPriceRepo = Mockery::mock(PlanPriceRepositoryInterface::class);

    $useCase = new CreateSubscription($subscriptionRepo, $planVersionRepo, $planPriceRepo);

    $dto = new CreateSubscriptionDTO(
        tenantId: $tenantId->value(),
        planVersionId: $planVersionId->value(),
        billingCycle: 'monthly',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(SubscriptionDTO::class)
        ->and($result->tenantId)->toBe($tenantId->value())
        ->and($result->planVersionId)->toBe($planVersionId->value())
        ->and($result->status)->toBe(SubscriptionStatus::Active->value);
});

test('throws when plan version is inactive', function () {
    $tenantId = Uuid::generate();
    $planVersionId = Uuid::generate();

    $inactivePlanVersion = new PlanVersion(
        id: $planVersionId,
        planId: Uuid::generate(),
        version: 1,
        status: PlanStatus::Inactive,
        createdAt: new DateTimeImmutable,
    );

    $subscriptionRepo = Mockery::mock(SubscriptionRepositoryInterface::class);
    $subscriptionRepo->expects('findActiveByTenantId')->andReturnNull();

    $planVersionRepo = Mockery::mock(PlanVersionRepositoryInterface::class);
    $planVersionRepo->expects('findById')->andReturn($inactivePlanVersion);

    $planPriceRepo = Mockery::mock(PlanPriceRepositoryInterface::class);

    $useCase = new CreateSubscription($subscriptionRepo, $planVersionRepo, $planPriceRepo);

    $dto = new CreateSubscriptionDTO(
        tenantId: $tenantId->value(),
        planVersionId: $planVersionId->value(),
        billingCycle: 'monthly',
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('PLAN_VERSION_NOT_AVAILABLE')
            ->and($e->context())->toHaveKey('plan_version_id', $planVersionId->value());
    }
});
