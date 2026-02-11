<?php

declare(strict_types=1);

namespace Application\Auth\DTOs;

final readonly class LoginRequestDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public string $ipAddress,
        public string $userAgent,
    ) {}
}
