<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use Domain\Auth\Contracts\PasswordHasherInterface;

final class BcryptPasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plainText): string
    {
        return password_hash($plainText, PASSWORD_BCRYPT);
    }

    public function verify(string $plainText, string $hash): bool
    {
        return password_verify($plainText, $hash);
    }
}
