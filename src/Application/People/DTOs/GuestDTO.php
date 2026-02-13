<?php

declare(strict_types=1);

namespace Application\People\DTOs;

final readonly class GuestDTO
{
    public function __construct(
        public string $id,
        public string $reservationId,
        public string $name,
        public ?string $document,
        public ?string $phone,
        public ?string $vehiclePlate,
        public ?string $relationship,
        public string $status,
        public ?string $checkedInAt,
        public ?string $checkedOutAt,
        public ?string $checkedInBy,
        public ?string $deniedBy,
        public ?string $deniedReason,
        public string $registeredBy,
        public string $createdAt,
    ) {}
}
