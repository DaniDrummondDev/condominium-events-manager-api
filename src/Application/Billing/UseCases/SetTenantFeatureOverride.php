<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\FeatureRepositoryInterface;
use Application\Billing\Contracts\TenantFeatureOverrideRepositoryInterface;
use Application\Billing\DTOs\CreateFeatureOverrideDTO;
use DateTimeImmutable;
use Domain\Billing\Entities\TenantFeatureOverride;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class SetTenantFeatureOverride
{
    public function __construct(
        private TenantFeatureOverrideRepositoryInterface $overrideRepository,
        private FeatureRepositoryInterface $featureRepository,
    ) {}

    public function execute(CreateFeatureOverrideDTO $dto): TenantFeatureOverride
    {
        $tenantId = Uuid::fromString($dto->tenantId);
        $featureId = Uuid::fromString($dto->featureId);

        $feature = $this->featureRepository->findById($featureId);

        if ($feature === null) {
            throw new DomainException(
                'Feature not found',
                'FEATURE_NOT_FOUND',
                ['feature_id' => $dto->featureId],
            );
        }

        $existing = $this->overrideRepository->findByTenantAndFeature($tenantId, $featureId);

        if ($existing !== null) {
            $this->overrideRepository->delete($existing->id());
        }

        $override = new TenantFeatureOverride(
            id: Uuid::generate(),
            tenantId: $tenantId,
            featureId: $featureId,
            value: $dto->value,
            reason: $dto->reason,
            expiresAt: $dto->expiresAt ? new DateTimeImmutable($dto->expiresAt) : null,
            createdBy: $dto->createdBy ? Uuid::fromString($dto->createdBy) : Uuid::generate(),
            createdAt: new DateTimeImmutable,
        );

        $this->overrideRepository->save($override);

        return $override;
    }
}
