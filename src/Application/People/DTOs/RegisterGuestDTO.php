<?php

declare(strict_types=1);

namespace Application\People\DTOs;

final readonly class RegisterGuestDTO
{
    public function __construct(
        public string $reservationId,
        public string $name,
        public ?string $document,
        public ?string $phone,
        public ?string $vehiclePlate,
        public ?string $relationship,
        public string $registeredBy,
    ) {}
}
