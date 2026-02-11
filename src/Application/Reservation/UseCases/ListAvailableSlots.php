<?php

declare(strict_types=1);

namespace Application\Reservation\UseCases;

use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Reservation\DTOs\AvailableSlotDTO;
use Application\Reservation\DTOs\ListAvailableSlotsDTO;
use Application\Space\Contracts\SpaceAvailabilityRepositoryInterface;
use Application\Space\Contracts\SpaceBlockRepositoryInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use DateTimeImmutable;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\DateRange;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ListAvailableSlots
{
    public function __construct(
        private SpaceRepositoryInterface $spaceRepository,
        private SpaceAvailabilityRepositoryInterface $availabilityRepository,
        private SpaceBlockRepositoryInterface $blockRepository,
        private ReservationRepositoryInterface $reservationRepository,
    ) {}

    /**
     * @return array<AvailableSlotDTO>
     */
    public function execute(ListAvailableSlotsDTO $dto): array
    {
        $spaceId = Uuid::fromString($dto->spaceId);
        $date = new DateTimeImmutable($dto->date);

        $space = $this->spaceRepository->findById($spaceId);

        if ($space === null) {
            throw new DomainException('Space not found', 'SPACE_NOT_FOUND', ['space_id' => $dto->spaceId]);
        }

        // Get availability windows for the day of week
        $dayOfWeek = (int) $date->format('w');
        $availabilities = $this->availabilityRepository->findBySpaceId($spaceId);

        $dayAvailabilities = array_filter(
            $availabilities,
            fn ($a) => $a->dayOfWeek() === $dayOfWeek,
        );

        if (count($dayAvailabilities) === 0) {
            return [];
        }

        // Get existing reservations for the date
        $dayStart = $date->setTime(0, 0);
        $dayEnd = $date->setTime(23, 59, 59);
        $dayRange = new DateRange($dayStart, $dayEnd);

        $reservations = $this->reservationRepository->findConflicting($spaceId, $dayRange);
        $blocks = $this->blockRepository->findBySpaceId($spaceId);

        // Generate slots from availability windows (1-hour intervals)
        $slots = [];

        foreach ($dayAvailabilities as $availability) {
            $slotStart = $date->modify($availability->startTime());
            $slotEndLimit = $date->modify($availability->endTime());

            while ($slotStart < $slotEndLimit) {
                $slotEnd = $slotStart->modify('+1 hour');

                if ($slotEnd > $slotEndLimit) {
                    $slotEnd = $slotEndLimit;
                }

                $slotRange = new DateRange($slotStart, $slotEnd);
                $available = true;

                // Check reservations
                foreach ($reservations as $reservation) {
                    if ($reservation->period()->overlaps($slotRange)) {
                        $available = false;

                        break;
                    }
                }

                // Check blocks
                if ($available) {
                    foreach ($blocks as $block) {
                        $blockRange = new DateRange($block->startDatetime(), $block->endDatetime());

                        if ($slotRange->overlaps($blockRange)) {
                            $available = false;

                            break;
                        }
                    }
                }

                $slots[] = new AvailableSlotDTO(
                    startTime: $slotStart->format('H:i'),
                    endTime: $slotEnd->format('H:i'),
                    available: $available,
                );

                $slotStart = $slotEnd;
            }
        }

        return $slots;
    }
}
