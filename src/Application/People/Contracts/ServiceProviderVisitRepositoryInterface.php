<?php

declare(strict_types=1);

namespace Application\People\Contracts;

use Domain\People\Entities\ServiceProviderVisit;
use Domain\Shared\ValueObjects\Uuid;

interface ServiceProviderVisitRepositoryInterface
{
    public function findById(Uuid $id): ?ServiceProviderVisit;

    /**
     * @return array<ServiceProviderVisit>
     */
    public function findByServiceProvider(Uuid $serviceProviderId): array;

    /**
     * @return array<ServiceProviderVisit>
     */
    public function findByUnit(Uuid $unitId): array;

    public function save(ServiceProviderVisit $visit): void;
}
