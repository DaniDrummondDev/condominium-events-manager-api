<?php

declare(strict_types=1);

namespace Application\People\UseCases;

use Application\People\Contracts\ServiceProviderRepositoryInterface;
use Application\People\Contracts\ServiceProviderVisitRepositoryInterface;
use Application\People\DTOs\ScheduleVisitDTO;
use Application\People\DTOs\ServiceProviderVisitDTO;
use Application\Unit\Contracts\UnitRepositoryInterface;
use DateTimeImmutable;
use Domain\People\Entities\ServiceProviderVisit;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ScheduleServiceProviderVisit
{
    public function __construct(
        private ServiceProviderVisitRepositoryInterface $visitRepository,
        private ServiceProviderRepositoryInterface $serviceProviderRepository,
        private UnitRepositoryInterface $unitRepository,
    ) {}

    public function execute(ScheduleVisitDTO $dto): ServiceProviderVisitDTO
    {
        $provider = $this->serviceProviderRepository->findById(Uuid::fromString($dto->serviceProviderId));

        if ($provider === null) {
            throw new DomainException(
                'Service provider not found',
                'SERVICE_PROVIDER_NOT_FOUND',
                ['service_provider_id' => $dto->serviceProviderId],
            );
        }

        if (! $provider->canBeLinkedToVisits()) {
            throw new DomainException(
                'Service provider is not active and cannot be linked to visits',
                'PROVIDER_NOT_ACTIVE',
                [
                    'service_provider_id' => $dto->serviceProviderId,
                    'status' => $provider->status()->value,
                ],
            );
        }

        $unit = $this->unitRepository->findById(Uuid::fromString($dto->unitId));

        if ($unit === null) {
            throw new DomainException(
                'Unit not found',
                'UNIT_NOT_FOUND',
                ['unit_id' => $dto->unitId],
            );
        }

        if (! $unit->isActive()) {
            throw new DomainException(
                'Unit is not active',
                'UNIT_NOT_ACTIVE',
                ['unit_id' => $dto->unitId],
            );
        }

        $visit = ServiceProviderVisit::create(
            id: Uuid::generate(),
            serviceProviderId: Uuid::fromString($dto->serviceProviderId),
            unitId: Uuid::fromString($dto->unitId),
            reservationId: $dto->reservationId ? Uuid::fromString($dto->reservationId) : null,
            scheduledDate: new DateTimeImmutable($dto->scheduledDate),
            purpose: $dto->purpose,
            notes: $dto->notes,
        );

        $this->visitRepository->save($visit);

        return self::toDTO($visit);
    }

    public static function toDTO(ServiceProviderVisit $visit): ServiceProviderVisitDTO
    {
        return new ServiceProviderVisitDTO(
            id: $visit->id()->value(),
            serviceProviderId: $visit->serviceProviderId()->value(),
            unitId: $visit->unitId()->value(),
            reservationId: $visit->reservationId()?->value(),
            scheduledDate: $visit->scheduledDate()->format('Y-m-d'),
            purpose: $visit->purpose(),
            status: $visit->status()->value,
            checkedInAt: $visit->checkedInAt()?->format('c'),
            checkedOutAt: $visit->checkedOutAt()?->format('c'),
            checkedInBy: $visit->checkedInBy()?->value(),
            notes: $visit->notes(),
            createdAt: $visit->createdAt()->format('c'),
        );
    }
}
