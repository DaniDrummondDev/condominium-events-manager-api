<?php

declare(strict_types=1);

namespace Application\People\DTOs;

final readonly class ServiceProviderVisitDTO
{
    public function __construct(
        public string $id,
        public string $serviceProviderId,
        public string $unitId,
        public ?string $reservationId,
        public string $scheduledDate,
        public string $purpose,
        public string $status,
        public ?string $checkedInAt,
        public ?string $checkedOutAt,
        public ?string $checkedInBy,
        public ?string $notes,
        public string $createdAt,
    ) {}
}
