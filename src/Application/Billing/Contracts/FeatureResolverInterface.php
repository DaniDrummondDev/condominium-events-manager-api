<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use Domain\Shared\ValueObjects\Uuid;

interface FeatureResolverInterface
{
    public function resolve(Uuid $tenantId, string $featureCode): ?string;

    /**
     * @return array<string, string|null>
     */
    public function resolveAll(Uuid $tenantId): array;

    public function hasFeature(Uuid $tenantId, string $featureCode): bool;

    public function featureLimit(Uuid $tenantId, string $featureCode): int;
}
