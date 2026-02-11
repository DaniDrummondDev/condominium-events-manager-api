<?php

declare(strict_types=1);

namespace Application\Auth\Contracts;

interface TotpServiceInterface
{
    public function generateSecret(): string;

    public function generateQrCodeUri(string $secret, string $email, string $issuer): string;

    public function verify(string $secret, string $code): bool;
}
