<?php

declare(strict_types=1);

namespace Application\People\DTOs;

final readonly class ServiceProviderDTO
{
    public function __construct(
        public string $id,
        public ?string $companyName,
        public string $name,
        public string $document,
        public ?string $phone,
        public string $serviceType,
        public string $status,
        public ?string $notes,
        public string $createdBy,
        public string $createdAt,
    ) {}
}
