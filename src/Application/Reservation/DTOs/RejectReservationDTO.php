<?php

declare(strict_types=1);

namespace Application\Reservation\DTOs;

final readonly class RejectReservationDTO
{
    public function __construct(
        public string $reservationId,
        public string $rejectedBy,
        public string $rejectionReason,
    ) {}
}
