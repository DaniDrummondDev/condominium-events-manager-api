<?php

declare(strict_types=1);

use Domain\Auth\ValueObjects\TokenClaims;
use Domain\Auth\ValueObjects\TokenType;
use Domain\Shared\ValueObjects\Uuid;

it('creates access token claims', function () {
    $userId = Uuid::generate();
    $tenantId = Uuid::generate();
    $now = new DateTimeImmutable;

    $claims = TokenClaims::forAccess(
        userId: $userId,
        tenantId: $tenantId,
        roles: ['platform_admin'],
        now: $now,
    );

    expect($claims->sub)->toBe($userId)
        ->and($claims->tenantId)->toBe($tenantId)
        ->and($claims->roles)->toBe(['platform_admin'])
        ->and($claims->tokenType)->toBe(TokenType::Access)
        ->and((string) $claims->jti)->toStartWith('tok_')
        ->and($claims->issuedAt)->toBe($now);
});

it('creates mfa required token claims', function () {
    $userId = Uuid::generate();
    $now = new DateTimeImmutable;

    $claims = TokenClaims::forMfaRequired(
        userId: $userId,
        tenantId: null,
        roles: ['platform_owner'],
        now: $now,
    );

    expect($claims->tokenType)->toBe(TokenType::MfaRequired)
        ->and((string) $claims->jti)->toStartWith('mfa_')
        ->and($claims->tenantId)->toBeNull();
});

it('has correct issuer and audience constants', function () {
    expect(TokenClaims::ISSUER)->toBe('condominium-events-api')
        ->and(TokenClaims::AUDIENCE_CLIENT)->toBe('condominium-events-client');
});

it('expires correctly for access tokens', function () {
    $now = new DateTimeImmutable;
    $claims = TokenClaims::forAccess(
        userId: Uuid::generate(),
        tenantId: null,
        roles: ['platform_admin'],
        now: $now,
    );

    $expectedExpiry = $now->modify('+900 seconds');
    expect($claims->expiresAt->getTimestamp())->toBe($expectedExpiry->getTimestamp());
});

it('expires correctly for mfa tokens', function () {
    $now = new DateTimeImmutable;
    $claims = TokenClaims::forMfaRequired(
        userId: Uuid::generate(),
        tenantId: null,
        roles: ['platform_admin'],
        now: $now,
    );

    $expectedExpiry = $now->modify('+300 seconds');
    expect($claims->expiresAt->getTimestamp())->toBe($expectedExpiry->getTimestamp());
});
