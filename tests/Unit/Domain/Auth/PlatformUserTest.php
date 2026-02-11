<?php

declare(strict_types=1);

use Domain\Auth\Contracts\PasswordHasherInterface;
use Domain\Auth\Entities\PlatformUser;
use Domain\Auth\Enums\PlatformRole;
use Domain\Auth\Enums\UserStatus;
use Domain\Shared\ValueObjects\Uuid;

function createPlatformUser(
    ?PlatformRole $role = null,
    ?UserStatus $status = null,
    int $failedAttempts = 0,
    ?DateTimeImmutable $lockedUntil = null,
    bool $mfaEnabled = false,
    ?string $mfaSecret = null,
): PlatformUser {
    return new PlatformUser(
        id: Uuid::generate(),
        email: 'admin@test.com',
        name: 'Admin',
        passwordHash: password_hash('Password1', PASSWORD_BCRYPT),
        role: $role ?? PlatformRole::PlatformAdmin,
        status: $status ?? UserStatus::Active,
        mfaEnabled: $mfaEnabled,
        mfaSecret: $mfaSecret,
        failedLoginAttempts: $failedAttempts,
        lockedUntil: $lockedUntil,
    );
}

it('verifies correct password', function () {
    $user = createPlatformUser();
    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')->with('Password1', Mockery::any())->andReturnTrue();

    expect($user->verifyPassword('Password1', $hasher))->toBeTrue();
});

it('rejects wrong password', function () {
    $user = createPlatformUser();
    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')->with('wrong', Mockery::any())->andReturnFalse();

    expect($user->verifyPassword('wrong', $hasher))->toBeFalse();
});

it('is not locked when lockedUntil is null', function () {
    $user = createPlatformUser();
    expect($user->isLocked(new DateTimeImmutable))->toBeFalse();
});

it('is locked when lockedUntil is in the future', function () {
    $now = new DateTimeImmutable;
    $user = createPlatformUser(lockedUntil: $now->modify('+10 minutes'));

    expect($user->isLocked($now))->toBeTrue();
});

it('is not locked when lockedUntil is in the past', function () {
    $now = new DateTimeImmutable;
    $user = createPlatformUser(lockedUntil: $now->modify('-1 minute'));

    expect($user->isLocked($now))->toBeFalse();
});

it('locks account after 10 failed attempts', function () {
    $user = createPlatformUser(failedAttempts: 9);
    $now = new DateTimeImmutable;

    $user->incrementFailedAttempts($now);

    expect($user->failedLoginAttempts())->toBe(10)
        ->and($user->isLocked($now))->toBeTrue();
});

it('does not lock before 10 attempts', function () {
    $user = createPlatformUser(failedAttempts: 8);
    $now = new DateTimeImmutable;

    $user->incrementFailedAttempts($now);

    expect($user->failedLoginAttempts())->toBe(9)
        ->and($user->isLocked($now))->toBeFalse();
});

it('resets failed attempts on recordLogin', function () {
    $user = createPlatformUser(failedAttempts: 5);
    $now = new DateTimeImmutable;

    $user->recordLogin($now);

    expect($user->failedLoginAttempts())->toBe(0)
        ->and($user->lastLoginAt())->toBe($now);
});

it('reports requiresMfa for platform_owner role', function () {
    $user = createPlatformUser(role: PlatformRole::PlatformOwner);
    expect($user->requiresMfa())->toBeTrue();
});

it('reports requiresMfa when mfa is enabled', function () {
    $user = createPlatformUser(role: PlatformRole::PlatformSupport, mfaEnabled: true);
    expect($user->requiresMfa())->toBeTrue();
});

it('does not require MFA for support without mfa enabled', function () {
    $user = createPlatformUser(role: PlatformRole::PlatformSupport);
    expect($user->requiresMfa())->toBeFalse();
});

it('reports hasMfaConfigured correctly', function () {
    $userWithMfa = createPlatformUser(mfaEnabled: true, mfaSecret: 'SECRET123');
    $userWithoutMfa = createPlatformUser(mfaEnabled: false);

    expect($userWithMfa->hasMfaConfigured())->toBeTrue()
        ->and($userWithoutMfa->hasMfaConfigured())->toBeFalse();
});

it('enables MFA with secret', function () {
    $user = createPlatformUser();
    $user->enableMfa('NEWSECRET');

    expect($user->mfaEnabled())->toBeTrue()
        ->and($user->mfaSecret())->toBe('NEWSECRET');
});

it('calculates lockout remaining minutes', function () {
    $now = new DateTimeImmutable;
    $user = createPlatformUser(lockedUntil: $now->modify('+15 minutes'));

    expect($user->lockoutRemainingMinutes($now))->toBe(15);
});
