<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Jwt;

use Application\Auth\Contracts\TokenRevocationInterface;
use DateTimeImmutable;
use Domain\Auth\ValueObjects\JtiToken;
use Illuminate\Support\Facades\Cache;

class RedisTokenRevocation implements TokenRevocationInterface
{
    private const string CACHE_PREFIX = 'revoked_token:';

    public function revoke(JtiToken $jti, DateTimeImmutable $expiresAt): void
    {
        $now = new DateTimeImmutable;
        $remainingSeconds = $expiresAt->getTimestamp() - $now->getTimestamp();

        if ($remainingSeconds <= 0) {
            return; // Token already expired, no need to revoke
        }

        Cache::put(
            self::CACHE_PREFIX.$jti->value(),
            $now->getTimestamp(),
            $remainingSeconds,
        );
    }

    public function isRevoked(JtiToken $jti): bool
    {
        return Cache::has(self::CACHE_PREFIX.$jti->value());
    }
}
