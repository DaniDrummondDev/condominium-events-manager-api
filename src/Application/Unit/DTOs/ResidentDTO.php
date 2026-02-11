<?php

declare(strict_types=1);

namespace Application\Unit\DTOs;

final readonly class ResidentDTO
{
    public function __construct(
        public string $id,
        public string $unitId,
        public string $tenantUserId,
        public string $name,
        public string $email,
        public ?string $phone,
        public string $roleInUnit,
        public bool $isPrimary,
        public string $status,
        public string $movedInAt,
        public ?string $movedOutAt,
    ) {}
}
