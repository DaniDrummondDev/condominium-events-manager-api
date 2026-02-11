<?php

declare(strict_types=1);

namespace Application\Unit\UseCases;

use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Unit\Contracts\ResidentRepositoryInterface;
use Application\Unit\Contracts\UnitRepositoryInterface;
use DateTimeImmutable;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class DeactivateUnit
{
    public function __construct(
        private UnitRepositoryInterface $unitRepository,
        private ResidentRepositoryInterface $residentRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(string $unitId): void
    {
        $id = Uuid::fromString($unitId);
        $unit = $this->unitRepository->findById($id);

        if ($unit === null) {
            throw new DomainException(
                'Unit not found',
                'UNIT_NOT_FOUND',
                ['unit_id' => $unitId],
            );
        }

        $activeResidents = $this->residentRepository->findActiveByUnitId($id);

        foreach ($activeResidents as $resident) {
            $resident->moveOut(new DateTimeImmutable);
            $this->residentRepository->save($resident);
            $this->eventDispatcher->dispatchAll($resident->pullDomainEvents());
        }

        $unit->deactivate();
        $unit->markVacant();
        $this->unitRepository->save($unit);
        $this->eventDispatcher->dispatchAll($unit->pullDomainEvents());
    }
}
