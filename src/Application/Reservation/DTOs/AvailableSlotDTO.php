<?php

declare(strict_types=1);

namespace Application\Reservation\DTOs;

final readonly class AvailableSlotDTO
{
    public function __construct(
        public string $startTime,
        public string $endTime,
        public bool $available,
    ) {}
}
