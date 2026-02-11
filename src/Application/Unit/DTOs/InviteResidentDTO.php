<?php

declare(strict_types=1);

namespace Application\Unit\DTOs;

final readonly class InviteResidentDTO
{
    public function __construct(
        public string $unitId,
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $document,
        public string $roleInUnit,
    ) {}
}
