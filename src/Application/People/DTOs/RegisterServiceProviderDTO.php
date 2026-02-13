<?php

declare(strict_types=1);

namespace Application\People\DTOs;

final readonly class RegisterServiceProviderDTO
{
    public function __construct(
        public ?string $companyName,
        public string $name,
        public string $document,
        public ?string $phone,
        public string $serviceType,
        public ?string $notes,
        public string $createdBy,
    ) {}
}
