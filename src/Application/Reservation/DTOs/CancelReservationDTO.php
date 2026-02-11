<?php

declare(strict_types=1);

namespace Application\Reservation\DTOs;

final readonly class CancelReservationDTO
{
    public function __construct(
        public string $reservationId,
        public string $canceledBy,
        public string $cancellationReason,
    ) {}
}
