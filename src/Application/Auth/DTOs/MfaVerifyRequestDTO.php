<?php

declare(strict_types=1);

namespace Application\Auth\DTOs;

final readonly class MfaVerifyRequestDTO
{
    public function __construct(
        public string $mfaToken,
        public string $code,
        public string $ipAddress,
        public string $userAgent,
    ) {}
}
