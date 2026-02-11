<?php

declare(strict_types=1);

namespace Application\Reservation\DTOs;

final readonly class ListAvailableSlotsDTO
{
    public function __construct(
        public string $spaceId,
        public string $date,
    ) {}
}
