<?php

declare(strict_types=1);

use Tests\Traits\CreatesBillingData;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class, CreatesBillingData::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();
});

test('creates a plan with multiple prices via use case', function () {
    $useCase = app(\Application\Billing\UseCases\CreatePlan::class);

    $result = $useCase->execute(new \Application\Billing\DTOs\CreatePlanDTO(
        name: 'Enterprise',
        slug: 'enterprise',
        prices: [
            ['billing_cycle' => 'monthly', 'price_in_cents' => 29900, 'currency' => 'BRL', 'trial_days' => 14],
            ['billing_cycle' => 'yearly', 'price_in_cents' => 299000, 'currency' => 'BRL', 'trial_days' => 14],
        ],
        features: [
            ['feature_key' => 'max_units', 'value' => '100', 'type' => 'integer'],
        ],
    ));

    expect($result->name)->toBe('Enterprise')
        ->and($result->slug)->toBe('enterprise')
        ->and($result->status)->toBe('active')
        ->and($result->currentVersion)->not->toBeNull()
        ->and($result->currentVersion->prices)->toHaveCount(2);

    $monthly = collect($result->currentVersion->prices)->firstWhere('billingCycle', 'monthly');
    $yearly = collect($result->currentVersion->prices)->firstWhere('billingCycle', 'yearly');

    expect($monthly->priceInCents)->toBe(29900)
        ->and($monthly->trialDays)->toBe(14)
        ->and($yearly->priceInCents)->toBe(299000)
        ->and($yearly->trialDays)->toBe(14);
});

test('creates plan version with new prices', function () {
    $createPlan = app(\Application\Billing\UseCases\CreatePlan::class);
    $createVersion = app(\Application\Billing\UseCases\CreatePlanVersion::class);

    $plan = $createPlan->execute(new \Application\Billing\DTOs\CreatePlanDTO(
        name: 'Pro',
        slug: 'pro',
        prices: [
            ['billing_cycle' => 'monthly', 'price_in_cents' => 19900, 'currency' => 'BRL', 'trial_days' => 0],
        ],
    ));

    $version = $createVersion->execute(new \Application\Billing\DTOs\CreatePlanVersionDTO(
        planId: $plan->id,
        prices: [
            ['billing_cycle' => 'monthly', 'price_in_cents' => 24900, 'currency' => 'BRL', 'trial_days' => 0],
            ['billing_cycle' => 'yearly', 'price_in_cents' => 249000, 'currency' => 'BRL', 'trial_days' => 30],
        ],
    ));

    expect($version->version)->toBe(2)
        ->and($version->status)->toBe('active')
        ->and($version->prices)->toHaveCount(2);

    $monthly = collect($version->prices)->firstWhere('billingCycle', 'monthly');
    $yearly = collect($version->prices)->firstWhere('billingCycle', 'yearly');

    expect($monthly->priceInCents)->toBe(24900)
        ->and($yearly->priceInCents)->toBe(249000)
        ->and($yearly->trialDays)->toBe(30);
});

test('plan slug must be unique', function () {
    $useCase = app(\Application\Billing\UseCases\CreatePlan::class);

    $useCase->execute(new \Application\Billing\DTOs\CreatePlanDTO(
        name: 'Starter',
        slug: 'starter',
        prices: [
            ['billing_cycle' => 'monthly', 'price_in_cents' => 9900, 'currency' => 'BRL', 'trial_days' => 0],
        ],
    ));

    $useCase->execute(new \Application\Billing\DTOs\CreatePlanDTO(
        name: 'Starter 2',
        slug: 'starter',
        prices: [
            ['billing_cycle' => 'monthly', 'price_in_cents' => 14900, 'currency' => 'BRL', 'trial_days' => 0],
        ],
    ));
})->throws(\Domain\Shared\Exceptions\DomainException::class);

test('lists all plans from repository', function () {
    $useCase = app(\Application\Billing\UseCases\CreatePlan::class);

    $useCase->execute(new \Application\Billing\DTOs\CreatePlanDTO(
        name: 'Basic',
        slug: 'basic',
        prices: [
            ['billing_cycle' => 'monthly', 'price_in_cents' => 4900, 'currency' => 'BRL', 'trial_days' => 0],
        ],
    ));
    $useCase->execute(new \Application\Billing\DTOs\CreatePlanDTO(
        name: 'Pro',
        slug: 'pro',
        prices: [
            ['billing_cycle' => 'monthly', 'price_in_cents' => 19900, 'currency' => 'BRL', 'trial_days' => 0],
            ['billing_cycle' => 'yearly', 'price_in_cents' => 199000, 'currency' => 'BRL', 'trial_days' => 0],
        ],
    ));

    $repo = app(\Application\Billing\Contracts\PlanRepositoryInterface::class);
    $plans = $repo->findAll();

    expect($plans)->toHaveCount(2);
});

test('listing plans returns all pricing options per version', function () {
    $createPlan = app(\Application\Billing\UseCases\CreatePlan::class);

    $createPlan->execute(new \Application\Billing\DTOs\CreatePlanDTO(
        name: 'Premium',
        slug: 'premium',
        prices: [
            ['billing_cycle' => 'monthly', 'price_in_cents' => 49900, 'currency' => 'BRL', 'trial_days' => 7],
            ['billing_cycle' => 'semiannual', 'price_in_cents' => 269000, 'currency' => 'BRL', 'trial_days' => 7],
            ['billing_cycle' => 'yearly', 'price_in_cents' => 499000, 'currency' => 'BRL', 'trial_days' => 7],
        ],
        features: [
            ['feature_key' => 'max_units', 'value' => '500', 'type' => 'integer'],
        ],
    ));

    $priceRepo = app(\Application\Billing\Contracts\PlanPriceRepositoryInterface::class);
    $versionRepo = app(\Application\Billing\Contracts\PlanVersionRepositoryInterface::class);
    $planRepo = app(\Application\Billing\Contracts\PlanRepositoryInterface::class);

    $plans = $planRepo->findAll();
    expect($plans)->toHaveCount(1);

    $plan = $plans[0];
    $version = $versionRepo->findActiveByPlanId($plan->id());
    expect($version)->not->toBeNull();

    $prices = $priceRepo->findByPlanVersionId($version->id());
    expect($prices)->toHaveCount(3);

    $cycles = array_map(fn ($p) => $p->billingCycle()->value, $prices);
    expect($cycles)->toContain('monthly')
        ->toContain('semiannual')
        ->toContain('yearly');
});
