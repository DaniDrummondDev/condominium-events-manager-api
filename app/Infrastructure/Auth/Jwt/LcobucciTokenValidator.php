<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Jwt;

use Application\Auth\Contracts\TokenRevocationInterface;
use Application\Auth\Contracts\TokenValidatorInterface;
use DateTimeImmutable;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Auth\ValueObjects\JtiToken;
use Domain\Auth\ValueObjects\TokenClaims;
use Domain\Auth\ValueObjects\TokenType;
use Domain\Shared\ValueObjects\Uuid;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Validation\Validator;
use Psr\Clock\ClockInterface;

class LcobucciTokenValidator implements TokenValidatorInterface
{
    private readonly string $publicKeyPath;

    private readonly string $issuer;

    private readonly string $audience;

    public function __construct(
        private readonly TokenRevocationInterface $revocation,
    ) {
        /** @var string $publicKeyPath */
        $publicKeyPath = config('jwt.public_key_path');
        $this->publicKeyPath = $publicKeyPath;

        /** @var string $issuer */
        $issuer = config('jwt.issuer', TokenClaims::ISSUER);
        $this->issuer = $issuer;

        /** @var string $audience */
        $audience = config('jwt.audience', TokenClaims::AUDIENCE_CLIENT);
        $this->audience = $audience;
    }

    public function validate(string $jwt): TokenClaims
    {
        try {
            $parser = new Parser(new JoseEncoder);
            $token = $parser->parse($jwt);
        } catch (\Throwable) {
            throw AuthenticationException::invalidToken('Malformed token');
        }

        $key = InMemory::file($this->publicKeyPath);
        $signer = new Sha256;

        $validator = new Validator;

        try {
            $validator->assert($token, ...[
                new SignedWith($signer, $key),
                new IssuedBy($this->issuer),
                new PermittedFor($this->audience),
                new StrictValidAt(new class implements ClockInterface
                {
                    public function now(): DateTimeImmutable
                    {
                        return new DateTimeImmutable('now', new \DateTimeZone('UTC'));
                    }
                }),
            ]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'expired') || str_contains($e->getMessage(), 'used before')) {
                throw AuthenticationException::tokenExpired();
            }

            throw AuthenticationException::invalidToken($e->getMessage());
        }

        if (! $token instanceof UnencryptedToken) {
            throw AuthenticationException::invalidToken('Encrypted tokens are not supported');
        }

        $claims = $token->claims();

        $jtiString = $claims->get('jti', '');
        $jti = JtiToken::fromString($jtiString);

        if ($this->revocation->isRevoked($jti)) {
            throw AuthenticationException::tokenRevoked();
        }

        $tenantIdRaw = $claims->get('tenant_id');
        $tenantId = $tenantIdRaw !== null ? Uuid::fromString($tenantIdRaw) : null;

        /** @var array<string> $roles */
        $roles = $claims->get('roles', []);

        $tokenTypeValue = $claims->get('token_type', 'access');
        $tokenType = TokenType::from($tokenTypeValue);

        return new TokenClaims(
            sub: Uuid::fromString($claims->get('sub', '')),
            tenantId: $tenantId,
            roles: $roles,
            tokenType: $tokenType,
            jti: $jti,
            issuedAt: $claims->get('iat', new DateTimeImmutable),
            expiresAt: $claims->get('exp', new DateTimeImmutable),
        );
    }
}
