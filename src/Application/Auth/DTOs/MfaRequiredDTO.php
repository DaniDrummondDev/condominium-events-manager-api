<?php

declare(strict_types=1);

namespace Application\Auth\DTOs;

final readonly class MfaRequiredDTO
{
    /**
     * @param  array<string>  $mfaMethods
     */
    public function __construct(
        public bool $mfaRequired,
        public string $mfaToken,
        public int $mfaTokenExpiresIn,
        public array $mfaMethods = ['totp'],
    ) {}
}
