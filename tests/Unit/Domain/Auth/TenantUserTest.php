<?php

declare(strict_types=1);

use Domain\Auth\Contracts\PasswordHasherInterface;
use Domain\Auth\Entities\TenantUser;
use Domain\Auth\Enums\TenantRole;
use Domain\Auth\Enums\TenantUserStatus;
use Domain\Shared\ValueObjects\Uuid;

function createTenantUser(
    ?TenantRole $role = null,
    ?TenantUserStatus $status = null,
    int $failedAttempts = 0,
    ?DateTimeImmutable $lockedUntil = null,
    bool $mfaEnabled = false,
    ?string $mfaSecret = null,
): TenantUser {
    return new TenantUser(
        id: Uuid::generate(),
        email: 'morador@test.com',
        name: 'Morador',
        passwordHash: password_hash('Password1', PASSWORD_BCRYPT),
        role: $role ?? TenantRole::Condomino,
        status: $status ?? TenantUserStatus::Active,
        phone: '11999999999',
        mfaSecret: $mfaSecret,
        mfaEnabled: $mfaEnabled,
        failedLoginAttempts: $failedAttempts,
        lockedUntil: $lockedUntil,
    );
}

it('verifies correct password', function () {
    $user = createTenantUser();
    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')->with('Password1', Mockery::any())->andReturnTrue();

    expect($user->verifyPassword('Password1', $hasher))->toBeTrue();
});

it('rejects wrong password', function () {
    $user = createTenantUser();
    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')->with('wrong', Mockery::any())->andReturnFalse();

    expect($user->verifyPassword('wrong', $hasher))->toBeFalse();
});

it('is not locked when lockedUntil is null', function () {
    $user = createTenantUser();
    expect($user->isLocked(new DateTimeImmutable))->toBeFalse();
});

it('is locked when lockedUntil is in the future', function () {
    $now = new DateTimeImmutable;
    $user = createTenantUser(lockedUntil: $now->modify('+10 minutes'));

    expect($user->isLocked($now))->toBeTrue();
});

it('is not locked when lockedUntil is in the past', function () {
    $now = new DateTimeImmutable;
    $user = createTenantUser(lockedUntil: $now->modify('-1 minute'));

    expect($user->isLocked($now))->toBeFalse();
});

it('locks account after 10 failed attempts', function () {
    $user = createTenantUser(failedAttempts: 9);
    $now = new DateTimeImmutable;

    $user->incrementFailedAttempts($now);

    expect($user->failedLoginAttempts())->toBe(10)
        ->and($user->isLocked($now))->toBeTrue();
});

it('does not lock before 10 attempts', function () {
    $user = createTenantUser(failedAttempts: 8);
    $now = new DateTimeImmutable;

    $user->incrementFailedAttempts($now);

    expect($user->failedLoginAttempts())->toBe(9)
        ->and($user->isLocked($now))->toBeFalse();
});

it('resets failed attempts on recordLogin', function () {
    $user = createTenantUser(failedAttempts: 5);
    $now = new DateTimeImmutable;

    $user->recordLogin($now);

    expect($user->failedLoginAttempts())->toBe(0)
        ->and($user->lastLoginAt())->toBe($now);
});

it('requires MFA for sindico role', function () {
    $user = createTenantUser(role: TenantRole::Sindico);
    expect($user->requiresMfa())->toBeTrue();
});

it('requires MFA when mfa is enabled', function () {
    $user = createTenantUser(role: TenantRole::Condomino, mfaEnabled: true);
    expect($user->requiresMfa())->toBeTrue();
});

it('does not require MFA for condomino without mfa enabled', function () {
    $user = createTenantUser(role: TenantRole::Condomino);
    expect($user->requiresMfa())->toBeFalse();
});

it('reports hasMfaConfigured correctly', function () {
    $withMfa = createTenantUser(mfaEnabled: true, mfaSecret: 'SECRET123');
    $withoutMfa = createTenantUser(mfaEnabled: false);

    expect($withMfa->hasMfaConfigured())->toBeTrue()
        ->and($withoutMfa->hasMfaConfigured())->toBeFalse();
});

it('enables MFA with secret', function () {
    $user = createTenantUser();
    $user->enableMfa('NEWSECRET');

    expect($user->mfaEnabled())->toBeTrue()
        ->and($user->mfaSecret())->toBe('NEWSECRET');
});

it('has phone accessor', function () {
    $user = createTenantUser();
    expect($user->phone())->toBe('11999999999');
});

it('has status with canLogin check', function () {
    $active = createTenantUser(status: TenantUserStatus::Active);
    $invited = createTenantUser(status: TenantUserStatus::Invited);
    $blocked = createTenantUser(status: TenantUserStatus::Blocked);

    expect($active->status()->canLogin())->toBeTrue()
        ->and($invited->status()->canLogin())->toBeFalse()
        ->and($blocked->status()->canLogin())->toBeFalse();
});
