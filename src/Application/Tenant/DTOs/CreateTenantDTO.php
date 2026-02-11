<?php

declare(strict_types=1);

namespace Application\Tenant\DTOs;

final readonly class CreateTenantDTO
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $type,
    ) {}
}
