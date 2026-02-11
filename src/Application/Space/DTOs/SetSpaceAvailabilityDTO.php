<?php

declare(strict_types=1);

namespace Application\Space\DTOs;

final readonly class SetSpaceAvailabilityDTO
{
    public function __construct(
        public string $spaceId,
        public int $dayOfWeek,
        public string $startTime,
        public string $endTime,
    ) {}
}
