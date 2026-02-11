<?php

declare(strict_types=1);

namespace Application\Auth\DTOs;

final readonly class MfaSetupDTO
{
    /**
     * @param  array<string>  $recoveryCodes
     */
    public function __construct(
        public string $secret,
        public string $otpauthUri,
        public array $recoveryCodes,
    ) {}
}
