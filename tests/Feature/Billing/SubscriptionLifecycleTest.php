<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use Tests\Traits\CreatesBillingData;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class, CreatesBillingData::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();

    // Create a tenant
    TenantModel::query()->create([
        'id' => $this->tenantId = \Domain\Shared\ValueObjects\Uuid::generate()->value(),
        'slug' => 'test-condo',
        'name' => 'Test Condominium',
        'type' => 'vertical',
        'status' => 'active',
    ]);

    // Create a plan + version
    $data = $this->createPlanInDatabase('Starter', 'starter', 9900);
    $this->planVersionId = $data['version']->id;
});

test('creates subscription for tenant', function () {
    $useCase = app(\Application\Billing\UseCases\CreateSubscription::class);

    $result = $useCase->execute(new \Application\Billing\DTOs\CreateSubscriptionDTO(
        tenantId: $this->tenantId,
        planVersionId: $this->planVersionId,
        billingCycle: 'monthly',
    ));

    expect($result->tenantId)->toBe($this->tenantId)
        ->and($result->planVersionId)->toBe($this->planVersionId)
        ->and($result->status)->toBe('active')
        ->and($result->billingCycle)->toBe('monthly');
});

test('subscription is idempotent by tenant', function () {
    $useCase = app(\Application\Billing\UseCases\CreateSubscription::class);
    $dto = new \Application\Billing\DTOs\CreateSubscriptionDTO(
        tenantId: $this->tenantId,
        planVersionId: $this->planVersionId,
        billingCycle: 'monthly',
    );

    $first = $useCase->execute($dto);
    $second = $useCase->execute($dto);

    expect($first->id)->toBe($second->id);
});

test('cancels subscription', function () {
    $createUseCase = app(\Application\Billing\UseCases\CreateSubscription::class);
    $cancelUseCase = app(\Application\Billing\UseCases\CancelSubscription::class);

    $subscription = $createUseCase->execute(new \Application\Billing\DTOs\CreateSubscriptionDTO(
        tenantId: $this->tenantId,
        planVersionId: $this->planVersionId,
        billingCycle: 'monthly',
    ));

    $result = $cancelUseCase->execute(new \Application\Billing\DTOs\CancelSubscriptionDTO(
        subscriptionId: $subscription->id,
        cancellationType: 'immediate',
    ));

    expect($result->status)->toBe('canceled')
        ->and($result->canceledAt)->not->toBeNull();
});

test('renews subscription', function () {
    $createUseCase = app(\Application\Billing\UseCases\CreateSubscription::class);
    $renewUseCase = app(\Application\Billing\UseCases\RenewSubscription::class);

    $subscription = $createUseCase->execute(new \Application\Billing\DTOs\CreateSubscriptionDTO(
        tenantId: $this->tenantId,
        planVersionId: $this->planVersionId,
        billingCycle: 'monthly',
    ));

    $result = $renewUseCase->execute($subscription->id);

    expect($result->status)->toBe('active')
        ->and($result->currentPeriodStart)->not->toBe($subscription->currentPeriodStart);
});
