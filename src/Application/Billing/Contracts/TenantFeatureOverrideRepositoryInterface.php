<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use Domain\Billing\Entities\TenantFeatureOverride;
use Domain\Shared\ValueObjects\Uuid;

interface TenantFeatureOverrideRepositoryInterface
{
    /**
     * @return array<TenantFeatureOverride>
     */
    public function findByTenantId(Uuid $tenantId): array;

    public function findByTenantAndFeature(Uuid $tenantId, Uuid $featureId): ?TenantFeatureOverride;

    public function save(TenantFeatureOverride $override): void;

    public function delete(Uuid $id): void;
}
