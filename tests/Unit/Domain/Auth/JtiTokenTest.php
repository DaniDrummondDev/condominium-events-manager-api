<?php

declare(strict_types=1);

use Domain\Auth\ValueObjects\JtiToken;
use Domain\Auth\ValueObjects\TokenType;
use Domain\Shared\Exceptions\DomainException;

it('generates JTI with correct prefix for Access', function () {
    $jti = JtiToken::generate(TokenType::Access);

    expect((string) $jti)->toStartWith('tok_');
});

it('generates JTI with correct prefix for MfaRequired', function () {
    $jti = JtiToken::generate(TokenType::MfaRequired);

    expect((string) $jti)->toStartWith('mfa_');
});

it('creates from valid string', function () {
    $jti = JtiToken::fromString('tok_abc123');

    expect((string) $jti)->toBe('tok_abc123');
});

it('throws on empty string', function () {
    JtiToken::fromString('');
})->throws(DomainException::class);

it('generates unique JTIs', function () {
    $jti1 = JtiToken::generate(TokenType::Access);
    $jti2 = JtiToken::generate(TokenType::Access);

    expect((string) $jti1)->not->toBe((string) $jti2);
});
