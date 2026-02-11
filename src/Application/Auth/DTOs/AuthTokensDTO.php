<?php

declare(strict_types=1);

namespace Application\Auth\DTOs;

final readonly class AuthTokensDTO
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
        public string $tokenType = 'bearer',
    ) {}
}
