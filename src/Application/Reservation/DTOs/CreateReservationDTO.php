<?php

declare(strict_types=1);

namespace Application\Reservation\DTOs;

final readonly class CreateReservationDTO
{
    public function __construct(
        public string $spaceId,
        public string $unitId,
        public string $residentId,
        public ?string $title,
        public string $startDatetime,
        public string $endDatetime,
        public int $expectedGuests,
        public ?string $notes,
    ) {}
}
