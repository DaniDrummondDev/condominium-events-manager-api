<?php

declare(strict_types=1);

namespace Application\People\DTOs;

final readonly class ScheduleVisitDTO
{
    public function __construct(
        public string $serviceProviderId,
        public string $unitId,
        public ?string $reservationId,
        public string $scheduledDate,
        public string $purpose,
        public ?string $notes,
        public string $createdBy,
    ) {}
}
