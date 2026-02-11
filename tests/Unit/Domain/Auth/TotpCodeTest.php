<?php

declare(strict_types=1);

use Domain\Auth\ValueObjects\TotpCode;

it('accepts valid 6-digit code', function () {
    $code = new TotpCode('123456');
    expect($code->value())->toBe('123456');
});

it('converts to string', function () {
    $code = new TotpCode('654321');
    expect((string) $code)->toBe('654321');
});

it('rejects non-numeric code', function () {
    new TotpCode('abcdef');
})->throws(InvalidArgumentException::class, 'TOTP code must be exactly 6 digits');

it('rejects code shorter than 6 digits', function () {
    new TotpCode('12345');
})->throws(InvalidArgumentException::class, 'TOTP code must be exactly 6 digits');

it('rejects code longer than 6 digits', function () {
    new TotpCode('1234567');
})->throws(InvalidArgumentException::class, 'TOTP code must be exactly 6 digits');

it('rejects empty string', function () {
    new TotpCode('');
})->throws(InvalidArgumentException::class, 'TOTP code must be exactly 6 digits');

it('rejects code with spaces', function () {
    new TotpCode('123 56');
})->throws(InvalidArgumentException::class, 'TOTP code must be exactly 6 digits');
