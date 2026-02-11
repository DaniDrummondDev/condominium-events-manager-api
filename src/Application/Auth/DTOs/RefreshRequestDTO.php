<?php

declare(strict_types=1);

namespace Application\Auth\DTOs;

final readonly class RefreshRequestDTO
{
    public function __construct(
        public string $refreshToken,
        public string $ipAddress,
        public string $userAgent,
    ) {}
}
