<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Platform\Models\FeatureModel;
use App\Infrastructure\Persistence\Platform\Models\PlanFeatureModel;
use App\Infrastructure\Persistence\Platform\Models\PlatformUserModel;
use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use Application\Billing\Contracts\FeatureResolverInterface;
use Tests\Traits\CreatesBillingData;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class, CreatesBillingData::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();

    TenantModel::query()->create([
        'id' => $this->tenantId = \Domain\Shared\ValueObjects\Uuid::generate()->value(),
        'slug' => 'feature-condo',
        'name' => 'Feature Test Condo',
        'type' => 'vertical',
        'status' => 'active',
    ]);

    $data = $this->createPlanInDatabase('Pro', 'pro-feat', 19900);
    $this->planVersionId = $data['version']->id;

    // Create features
    $this->featureId = \Domain\Shared\ValueObjects\Uuid::generate()->value();
    FeatureModel::query()->create([
        'id' => $this->featureId,
        'code' => 'max_units',
        'name' => 'Max Units',
        'type' => 'integer',
    ]);

    // Assign feature to plan version
    PlanFeatureModel::query()->create([
        'id' => \Domain\Shared\ValueObjects\Uuid::generate()->value(),
        'plan_version_id' => $this->planVersionId,
        'feature_key' => 'max_units',
        'value' => '50',
        'type' => 'integer',
    ]);

    // Create subscription
    $createSub = app(\Application\Billing\UseCases\CreateSubscription::class);
    $createSub->execute(new \Application\Billing\DTOs\CreateSubscriptionDTO(
        tenantId: $this->tenantId,
        planVersionId: $this->planVersionId,
        billingCycle: 'monthly',
    ));
});

test('resolves plan feature for tenant', function () {
    $resolver = app(FeatureResolverInterface::class);
    $tenantId = \Domain\Shared\ValueObjects\Uuid::fromString($this->tenantId);

    $value = $resolver->resolve($tenantId, 'max_units');

    expect($value)->toBe('50');
});

test('feature limit returns integer', function () {
    $resolver = app(FeatureResolverInterface::class);
    $tenantId = \Domain\Shared\ValueObjects\Uuid::fromString($this->tenantId);

    $limit = $resolver->featureLimit($tenantId, 'max_units');

    expect($limit)->toBe(50);
});

test('returns null for unknown feature', function () {
    $resolver = app(FeatureResolverInterface::class);
    $tenantId = \Domain\Shared\ValueObjects\Uuid::fromString($this->tenantId);

    $value = $resolver->resolve($tenantId, 'unknown_feature');

    expect($value)->toBeNull();
});

test('tenant override takes precedence over plan feature', function () {
    // Create a platform user for the created_by FK
    $platformUserId = \Domain\Shared\ValueObjects\Uuid::generate()->value();
    PlatformUserModel::query()->create([
        'id' => $platformUserId,
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'password_hash' => 'hashed',
        'role' => 'platform_admin',
        'status' => 'active',
    ]);

    $overrideUseCase = app(\Application\Billing\UseCases\SetTenantFeatureOverride::class);
    $resolver = app(FeatureResolverInterface::class);

    $overrideUseCase->execute(new \Application\Billing\DTOs\CreateFeatureOverrideDTO(
        tenantId: $this->tenantId,
        featureId: $this->featureId,
        value: '200',
        reason: 'Enterprise customer needs more units',
        createdBy: $platformUserId,
    ));

    // Clear cache for this test
    \Illuminate\Support\Facades\Cache::flush();

    $tenantId = \Domain\Shared\ValueObjects\Uuid::fromString($this->tenantId);
    $limit = $resolver->featureLimit($tenantId, 'max_units');

    expect($limit)->toBe(200);
});
