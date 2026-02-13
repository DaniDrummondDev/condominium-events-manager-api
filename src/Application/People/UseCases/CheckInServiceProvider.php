<?php

declare(strict_types=1);

namespace Application\People\UseCases;

use Application\People\Contracts\ServiceProviderVisitRepositoryInterface;
use Application\People\DTOs\ServiceProviderVisitDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class CheckInServiceProvider
{
    public function __construct(
        private ServiceProviderVisitRepositoryInterface $visitRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(string $visitId, string $checkedInBy): ServiceProviderVisitDTO
    {
        $visit = $this->visitRepository->findById(Uuid::fromString($visitId));

        if ($visit === null) {
            throw new DomainException(
                'Service provider visit not found',
                'VISIT_NOT_FOUND',
                ['visit_id' => $visitId],
            );
        }

        $visit->checkIn(Uuid::fromString($checkedInBy));

        $this->visitRepository->save($visit);
        $this->eventDispatcher->dispatchAll($visit->pullDomainEvents());

        return ScheduleServiceProviderVisit::toDTO($visit);
    }
}
