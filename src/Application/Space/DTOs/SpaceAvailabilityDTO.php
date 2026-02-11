<?php

declare(strict_types=1);

namespace Application\Space\DTOs;

final readonly class SpaceAvailabilityDTO
{
    public function __construct(
        public string $id,
        public string $spaceId,
        public int $dayOfWeek,
        public string $startTime,
        public string $endTime,
    ) {}
}
