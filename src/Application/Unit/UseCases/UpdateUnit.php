<?php

declare(strict_types=1);

namespace Application\Unit\UseCases;

use Application\Unit\Contracts\UnitRepositoryInterface;
use Application\Unit\DTOs\UnitDTO;
use Application\Unit\DTOs\UpdateUnitDTO;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Enums\UnitType;

final readonly class UpdateUnit
{
    public function __construct(
        private UnitRepositoryInterface $unitRepository,
    ) {}

    public function execute(UpdateUnitDTO $dto): UnitDTO
    {
        $unit = $this->unitRepository->findById(Uuid::fromString($dto->unitId));

        if ($unit === null) {
            throw new DomainException(
                'Unit not found',
                'UNIT_NOT_FOUND',
                ['unit_id' => $dto->unitId],
            );
        }

        if ($dto->number !== null && $dto->number !== $unit->number()) {
            $existing = $this->unitRepository->findByNumber($dto->number, $unit->blockId());

            if ($existing !== null) {
                throw new DomainException(
                    "Unit with number '{$dto->number}' already exists",
                    'UNIT_NUMBER_DUPLICATE',
                    ['number' => $dto->number],
                );
            }

            $unit->updateNumber($dto->number);
        }

        if ($dto->floor !== null) {
            $unit->updateFloor($dto->floor);
        }

        if ($dto->type !== null) {
            $unit->updateType(UnitType::from($dto->type));
        }

        $this->unitRepository->save($unit);

        return new UnitDTO(
            id: $unit->id()->value(),
            blockId: $unit->blockId()?->value(),
            number: $unit->number(),
            floor: $unit->floor(),
            type: $unit->type()->value,
            status: $unit->status()->value,
            isOccupied: $unit->isOccupied(),
            createdAt: $unit->createdAt()->format('c'),
        );
    }
}
