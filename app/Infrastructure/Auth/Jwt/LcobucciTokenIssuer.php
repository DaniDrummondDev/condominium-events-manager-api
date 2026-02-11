<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Jwt;

use Application\Auth\Contracts\TokenIssuerInterface;
use Domain\Auth\ValueObjects\TokenClaims;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;

class LcobucciTokenIssuer implements TokenIssuerInterface
{
    private readonly string $privateKeyPath;

    private readonly string $issuer;

    private readonly string $audience;

    public function __construct()
    {
        /** @var string $privateKeyPath */
        $privateKeyPath = config('jwt.private_key_path');
        $this->privateKeyPath = $privateKeyPath;

        /** @var string $issuer */
        $issuer = config('jwt.issuer', TokenClaims::ISSUER);
        $this->issuer = $issuer;

        /** @var string $audience */
        $audience = config('jwt.audience', TokenClaims::AUDIENCE_CLIENT);
        $this->audience = $audience;
    }

    public function issue(TokenClaims $claims): string
    {
        $builder = new Builder(new JoseEncoder, ChainedFormatter::default());

        $key = InMemory::file($this->privateKeyPath);

        $builder = $builder
            ->issuedBy($this->issuer)
            ->permittedFor($this->audience)
            ->identifiedBy($claims->jti->value())
            ->issuedAt($claims->issuedAt)
            ->canOnlyBeUsedAfter($claims->issuedAt)
            ->expiresAt($claims->expiresAt)
            ->relatedTo($claims->sub->value())
            ->withClaim('tenant_id', $claims->tenantId?->value())
            ->withClaim('roles', $claims->roles)
            ->withClaim('token_type', $claims->tokenType->value);

        return $builder->getToken(new Sha256, $key)->toString();
    }
}
