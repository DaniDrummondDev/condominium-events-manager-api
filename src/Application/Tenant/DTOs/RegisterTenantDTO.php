<?php

declare(strict_types=1);

namespace Application\Tenant\DTOs;

final readonly class RegisterTenantDTO
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $type,
        public string $adminName,
        public string $adminEmail,
        public string $adminPassword,
        public ?string $adminPhone,
        public string $planSlug,
    ) {}
}
