<?php

declare(strict_types=1);

namespace Application\Auth\DTOs;

use DateTimeImmutable;

final readonly class AuthenticatedUserDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $role,
        public bool $mfaEnabled,
        public ?DateTimeImmutable $lastLoginAt,
    ) {}
}
