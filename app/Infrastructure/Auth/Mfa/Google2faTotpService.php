<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Mfa;

use Application\Auth\Contracts\TotpServiceInterface;
use PragmaRX\Google2FA\Google2FA;

class Google2faTotpService implements TotpServiceInterface
{
    private readonly Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function generateQrCodeUri(string $secret, string $email, string $issuer): string
    {
        return $this->google2fa->getQRCodeUrl($issuer, $email, $secret);
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }
}
