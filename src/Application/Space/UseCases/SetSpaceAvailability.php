<?php

declare(strict_types=1);

namespace Application\Space\UseCases;

use Application\Space\Contracts\SpaceAvailabilityRepositoryInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\DTOs\SetSpaceAvailabilityDTO;
use Application\Space\DTOs\SpaceAvailabilityDTO;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceAvailability;

final readonly class SetSpaceAvailability
{
    public function __construct(
        private SpaceRepositoryInterface $spaceRepository,
        private SpaceAvailabilityRepositoryInterface $availabilityRepository,
    ) {}

    public function execute(SetSpaceAvailabilityDTO $dto): SpaceAvailabilityDTO
    {
        $spaceId = Uuid::fromString($dto->spaceId);
        $space = $this->spaceRepository->findById($spaceId);

        if ($space === null) {
            throw new DomainException(
                'Space not found',
                'SPACE_NOT_FOUND',
                ['space_id' => $dto->spaceId],
            );
        }

        $newAvailability = SpaceAvailability::create(
            Uuid::generate(),
            $spaceId,
            $dto->dayOfWeek,
            $dto->startTime,
            $dto->endTime,
        );

        $existingWindows = $this->availabilityRepository->findBySpaceIdAndDay($spaceId, $dto->dayOfWeek);

        foreach ($existingWindows as $existing) {
            if ($newAvailability->overlaps($existing)) {
                throw new DomainException(
                    'Availability window overlaps with existing window',
                    'AVAILABILITY_OVERLAP',
                    [
                        'space_id' => $dto->spaceId,
                        'day_of_week' => $dto->dayOfWeek,
                        'existing_start' => $existing->startTime(),
                        'existing_end' => $existing->endTime(),
                    ],
                );
            }
        }

        $this->availabilityRepository->save($newAvailability);

        return new SpaceAvailabilityDTO(
            id: $newAvailability->id()->value(),
            spaceId: $newAvailability->spaceId()->value(),
            dayOfWeek: $newAvailability->dayOfWeek(),
            startTime: $newAvailability->startTime(),
            endTime: $newAvailability->endTime(),
        );
    }
}
