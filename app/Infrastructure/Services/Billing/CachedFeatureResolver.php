<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Billing;

use Application\Billing\Contracts\FeatureResolverInterface;
use Application\Billing\Contracts\PlanFeatureRepositoryInterface;
use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use Application\Billing\Contracts\TenantFeatureOverrideRepositoryInterface;
use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Support\Facades\Cache;

class CachedFeatureResolver implements FeatureResolverInterface
{
    private const int CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly TenantFeatureOverrideRepositoryInterface $overrideRepository,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly PlanFeatureRepositoryInterface $planFeatureRepository,
    ) {}

    public function resolve(Uuid $tenantId, string $featureCode): ?string
    {
        $all = $this->resolveAll($tenantId);

        return $all[$featureCode] ?? null;
    }

    /**
     * @return array<string, string|null>
     */
    public function resolveAll(Uuid $tenantId): array
    {
        $cacheKey = "features:tenant:{$tenantId->value()}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($tenantId) {
            return $this->buildFeatureMap($tenantId);
        });
    }

    public function hasFeature(Uuid $tenantId, string $featureCode): bool
    {
        $value = $this->resolve($tenantId, $featureCode);

        if ($value === null) {
            return false;
        }

        return $value === 'true' || $value === '1';
    }

    public function featureLimit(Uuid $tenantId, string $featureCode): int
    {
        $value = $this->resolve($tenantId, $featureCode);

        if ($value === null) {
            return 0;
        }

        return (int) $value;
    }

    /**
     * @return array<string, string|null>
     */
    private function buildFeatureMap(Uuid $tenantId): array
    {
        $features = [];

        $subscription = $this->subscriptionRepository->findActiveByTenantId($tenantId);

        if ($subscription !== null) {
            $planFeatures = $this->planFeatureRepository->findByPlanVersionId(
                $subscription->planVersionId(),
            );

            foreach ($planFeatures as $pf) {
                $features[$pf->featureKey()] = $pf->value();
            }
        }

        $now = new DateTimeImmutable;
        $overrides = $this->overrideRepository->findByTenantId($tenantId);

        foreach ($overrides as $override) {
            if (! $override->isExpired($now)) {
                $feature = $this->findFeatureCodeById($override->featureId());

                if ($feature !== null) {
                    $features[$feature] = $override->value();
                }
            }
        }

        return $features;
    }

    private function findFeatureCodeById(Uuid $featureId): ?string
    {
        $cacheKey = "feature:code:{$featureId->value()}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS * 2, function () use ($featureId) {
            $feature = app(\Application\Billing\Contracts\FeatureRepositoryInterface::class)
                ->findById($featureId);

            return $feature?->code();
        });
    }
}
