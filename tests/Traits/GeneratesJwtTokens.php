<?php

declare(strict_types=1);

namespace Tests\Traits;

use Domain\Auth\ValueObjects\JtiToken;
use Domain\Auth\ValueObjects\TokenClaims;
use Domain\Auth\ValueObjects\TokenType;
use Domain\Shared\ValueObjects\Uuid;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;

trait GeneratesJwtTokens
{
    protected function setUpJwtTestKeys(): void
    {
        config([
            'jwt.private_key_path' => base_path('tests/fixtures/jwt-test-private.pem'),
            'jwt.public_key_path' => base_path('tests/fixtures/jwt-test-public.pem'),
            'jwt.issuer' => TokenClaims::ISSUER,
            'jwt.audience' => TokenClaims::AUDIENCE_CLIENT,
        ]);
    }

    protected function generateAccessToken(
        ?Uuid $userId = null,
        ?Uuid $tenantId = null,
        array $roles = ['platform_admin'],
        ?int $ttlSeconds = null,
    ): string {
        $userId ??= Uuid::generate();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $ttl = $ttlSeconds ?? 900;

        return $this->buildToken(
            userId: $userId,
            tenantId: $tenantId,
            roles: $roles,
            tokenType: TokenType::Access,
            now: $now,
            ttl: $ttl,
        );
    }

    protected function generateMfaToken(
        ?Uuid $userId = null,
        array $roles = ['platform_admin'],
    ): string {
        $userId ??= Uuid::generate();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this->buildToken(
            userId: $userId,
            tenantId: null,
            roles: $roles,
            tokenType: TokenType::MfaRequired,
            now: $now,
            ttl: 300,
        );
    }

    protected function generateExpiredToken(?Uuid $userId = null): string
    {
        $userId ??= Uuid::generate();
        $now = new \DateTimeImmutable('-20 minutes', new \DateTimeZone('UTC'));

        return $this->buildToken(
            userId: $userId,
            tenantId: null,
            roles: ['platform_admin'],
            tokenType: TokenType::Access,
            now: $now,
            ttl: 900,
        );
    }

    private function buildToken(
        Uuid $userId,
        ?Uuid $tenantId,
        array $roles,
        TokenType $tokenType,
        \DateTimeImmutable $now,
        int $ttl,
    ): string {
        $builder = new Builder(new JoseEncoder, ChainedFormatter::default());
        $key = InMemory::file(base_path('tests/fixtures/jwt-test-private.pem'));
        $jti = JtiToken::generate($tokenType);

        $builder = $builder
            ->issuedBy(TokenClaims::ISSUER)
            ->permittedFor(TokenClaims::AUDIENCE_CLIENT)
            ->identifiedBy($jti->value())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify("+{$ttl} seconds"))
            ->relatedTo($userId->value())
            ->withClaim('tenant_id', $tenantId?->value())
            ->withClaim('roles', $roles)
            ->withClaim('token_type', $tokenType->value);

        return $builder->getToken(new Sha256, $key)->toString();
    }
}
