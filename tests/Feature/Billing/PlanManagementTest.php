<?php

declare(strict_types=1);

use Tests\Traits\CreatesBillingData;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class, CreatesBillingData::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();
});

test('creates a plan via use case', function () {
    $useCase = app(\Application\Billing\UseCases\CreatePlan::class);

    $result = $useCase->execute(new \Application\Billing\DTOs\CreatePlanDTO(
        name: 'Enterprise',
        slug: 'enterprise',
        priceInCents: 29900,
        currency: 'BRL',
        billingCycle: 'monthly',
        trialDays: 14,
        features: [
            ['feature_key' => 'max_units', 'value' => '100', 'type' => 'integer'],
        ],
    ));

    expect($result->name)->toBe('Enterprise')
        ->and($result->slug)->toBe('enterprise')
        ->and($result->status)->toBe('active')
        ->and($result->currentVersion)->not->toBeNull()
        ->and($result->currentVersion->priceInCents)->toBe(29900)
        ->and($result->currentVersion->billingCycle)->toBe('monthly')
        ->and($result->currentVersion->trialDays)->toBe(14);
});

test('creates plan version via use case', function () {
    $createPlan = app(\Application\Billing\UseCases\CreatePlan::class);
    $createVersion = app(\Application\Billing\UseCases\CreatePlanVersion::class);

    $plan = $createPlan->execute(new \Application\Billing\DTOs\CreatePlanDTO(
        name: 'Pro',
        slug: 'pro',
        priceInCents: 19900,
        currency: 'BRL',
        billingCycle: 'monthly',
        trialDays: 0,
    ));

    $version = $createVersion->execute(new \Application\Billing\DTOs\CreatePlanVersionDTO(
        planId: $plan->id,
        priceInCents: 24900,
        currency: 'BRL',
        billingCycle: 'yearly',
        trialDays: 30,
    ));

    expect($version->version)->toBe(2)
        ->and($version->priceInCents)->toBe(24900)
        ->and($version->billingCycle)->toBe('yearly')
        ->and($version->trialDays)->toBe(30)
        ->and($version->status)->toBe('active');
});

test('plan slug must be unique', function () {
    $useCase = app(\Application\Billing\UseCases\CreatePlan::class);

    $useCase->execute(new \Application\Billing\DTOs\CreatePlanDTO(
        name: 'Starter',
        slug: 'starter',
        priceInCents: 9900,
        currency: 'BRL',
        billingCycle: 'monthly',
        trialDays: 0,
    ));

    $useCase->execute(new \Application\Billing\DTOs\CreatePlanDTO(
        name: 'Starter 2',
        slug: 'starter',
        priceInCents: 14900,
        currency: 'BRL',
        billingCycle: 'monthly',
        trialDays: 0,
    ));
})->throws(\Domain\Shared\Exceptions\DomainException::class);

test('lists all plans from repository', function () {
    $useCase = app(\Application\Billing\UseCases\CreatePlan::class);

    $useCase->execute(new \Application\Billing\DTOs\CreatePlanDTO(
        name: 'Basic', slug: 'basic', priceInCents: 4900,
        currency: 'BRL', billingCycle: 'monthly', trialDays: 0,
    ));
    $useCase->execute(new \Application\Billing\DTOs\CreatePlanDTO(
        name: 'Pro', slug: 'pro', priceInCents: 19900,
        currency: 'BRL', billingCycle: 'monthly', trialDays: 0,
    ));

    $repo = app(\Application\Billing\Contracts\PlanRepositoryInterface::class);
    $plans = $repo->findAll();

    expect($plans)->toHaveCount(2);
});
