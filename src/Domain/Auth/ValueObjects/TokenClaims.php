<?php

declare(strict_types=1);

namespace Domain\Auth\ValueObjects;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;

final readonly class TokenClaims
{
    public const string ISSUER = 'condominium-events-api';

    public const string AUDIENCE_CLIENT = 'condominium-events-client';

    /**
     * @param  array<string>  $roles
     */
    public function __construct(
        public Uuid $sub,
        public ?Uuid $tenantId,
        public array $roles,
        public TokenType $tokenType,
        public JtiToken $jti,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
    ) {}

    /**
     * @param  array<string>  $roles
     */
    public static function forAccess(
        Uuid $userId,
        ?Uuid $tenantId,
        array $roles,
        ?DateTimeImmutable $now = null,
    ): self {
        $now ??= new DateTimeImmutable;
        $type = TokenType::Access;

        return new self(
            sub: $userId,
            tenantId: $tenantId,
            roles: $roles,
            tokenType: $type,
            jti: JtiToken::generate($type),
            issuedAt: $now,
            expiresAt: $now->modify('+'.$type->ttlSeconds().' seconds'),
        );
    }

    /**
     * @param  array<string>  $roles
     */
    public static function forMfaRequired(
        Uuid $userId,
        ?Uuid $tenantId,
        array $roles,
        ?DateTimeImmutable $now = null,
    ): self {
        $now ??= new DateTimeImmutable;
        $type = TokenType::MfaRequired;

        return new self(
            sub: $userId,
            tenantId: $tenantId,
            roles: $roles,
            tokenType: $type,
            jti: JtiToken::generate($type),
            issuedAt: $now,
            expiresAt: $now->modify('+'.$type->ttlSeconds().' seconds'),
        );
    }
}
