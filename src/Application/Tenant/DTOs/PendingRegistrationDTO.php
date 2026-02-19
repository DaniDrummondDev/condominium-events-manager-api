<?php

declare(strict_types=1);

namespace Application\Tenant\DTOs;

final readonly class PendingRegistrationDTO
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public string $type,
        public string $adminName,
        public string $adminEmail,
        public string $planSlug,
        public string $expiresAt,
    ) {}
}
