<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use Domain\Auth\ValueObjects\TokenClaims;
use Domain\Auth\ValueObjects\TokenType;
use Domain\Shared\ValueObjects\Uuid;

final readonly class AuthenticatedUser
{
    /**
     * @param  array<string>  $roles
     */
    public function __construct(
        public Uuid $userId,
        public ?Uuid $tenantId,
        public array $roles,
        public TokenType $tokenType,
    ) {}

    public static function fromClaims(TokenClaims $claims): self
    {
        return new self(
            userId: $claims->sub,
            tenantId: $claims->tenantId,
            roles: $claims->roles,
            tokenType: $claims->tokenType,
        );
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}
