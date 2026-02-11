<?php

declare(strict_types=1);

namespace Application\Reservation\DTOs;

final readonly class ReservationDetailDTO
{
    public function __construct(
        public ReservationDTO $reservation,
        public string $spaceName,
        public string $unitNumber,
        public string $residentName,
    ) {}
}
