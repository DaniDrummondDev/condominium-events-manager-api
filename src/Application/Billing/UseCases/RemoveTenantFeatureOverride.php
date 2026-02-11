<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\TenantFeatureOverrideRepositoryInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class RemoveTenantFeatureOverride
{
    public function __construct(
        private TenantFeatureOverrideRepositoryInterface $overrideRepository,
    ) {}

    public function execute(string $tenantId, string $overrideId): void
    {
        $tenantUuid = Uuid::fromString($tenantId);
        $overrides = $this->overrideRepository->findByTenantId($tenantUuid);

        $found = false;

        foreach ($overrides as $override) {
            if ($override->id()->value() === $overrideId) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            throw new DomainException(
                'Feature override not found',
                'FEATURE_OVERRIDE_NOT_FOUND',
                ['override_id' => $overrideId, 'tenant_id' => $tenantId],
            );
        }

        $this->overrideRepository->delete(Uuid::fromString($overrideId));
    }
}
