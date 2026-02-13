<?php

declare(strict_types=1);

namespace Application\People\DTOs;

final readonly class UpdateServiceProviderDTO
{
    public function __construct(
        public string $serviceProviderId,
        public ?string $companyName,
        public string $name,
        public ?string $phone,
        public string $serviceType,
        public ?string $notes,
    ) {}
}
