<?php

declare(strict_types=1);

use Application\Auth\Contracts\TenantUserRepositoryInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Unit\Contracts\ResidentRepositoryInterface;
use Application\Unit\UseCases\ActivateResident;
use Domain\Auth\Contracts\PasswordHasherInterface;
use Domain\Auth\Entities\TenantUser;
use Domain\Auth\Enums\TenantRole;
use Domain\Auth\Enums\TenantUserStatus;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Resident;
use Domain\Unit\Enums\ResidentRole;
use Domain\Unit\Events\ResidentActivated;

afterEach(fn () => Mockery::close());

function buildInvitedTenantUser(): TenantUser
{
    return new TenantUser(
        id: Uuid::generate(),
        email: 'invited@test.com',
        name: 'Convidado',
        passwordHash: '',
        role: TenantRole::Condomino,
        status: TenantUserStatus::Invited,
    );
}

function buildInvitedResident(Uuid $unitId, Uuid $tenantUserId): Resident
{
    return Resident::createInvited(
        Uuid::generate(),
        $unitId,
        $tenantUserId,
        'Convidado',
        'invited@test.com',
        null,
        ResidentRole::Owner,
        true,
    );
}

test('activates TenantUser and Resident on valid token', function () {
    $tenantUser = buildInvitedTenantUser();
    $unitId = Uuid::generate();
    $resident = buildInvitedResident($unitId, $tenantUser->id());
    $resident->pullDomainEvents();

    $futureDate = (new DateTimeImmutable)->modify('+48 hours');

    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $tenantUserRepo->shouldReceive('findByInvitationToken')
        ->with('valid-token-123')
        ->andReturn($tenantUser);
    $tenantUserRepo->shouldReceive('getInvitationExpiresAt')
        ->andReturn($futureDate);
    $tenantUserRepo->shouldReceive('save')
        ->once()
        ->withArgs(fn (TenantUser $u) => $u->status() === TenantUserStatus::Active
            && $u->passwordHash() === 'hashed-password',
        );
    $tenantUserRepo->shouldReceive('clearInvitationToken')
        ->once()
        ->withArgs(fn (Uuid $id) => $id->equals($tenantUser->id()));

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findByTenantUserId')
        ->andReturn([$resident]);
    $residentRepo->shouldReceive('save')
        ->once()
        ->withArgs(fn (Resident $r) => $r->status()->value === 'active');

    $passwordHasher = Mockery::mock(PasswordHasherInterface::class);
    $passwordHasher->shouldReceive('hash')
        ->with('MySecurePass1!')
        ->andReturn('hashed-password');

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(function (array $events) {
            return count($events) === 1 && $events[0] instanceof ResidentActivated;
        });

    $useCase = new ActivateResident($tenantUserRepo, $residentRepo, $passwordHasher, $eventDispatcher);
    $useCase->execute('valid-token-123', 'MySecurePass1!');
});

test('throws INVITATION_TOKEN_INVALID for invalid token', function () {
    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $tenantUserRepo->shouldReceive('findByInvitationToken')
        ->with('invalid-token')
        ->andReturnNull();

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $passwordHasher = Mockery::mock(PasswordHasherInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ActivateResident($tenantUserRepo, $residentRepo, $passwordHasher, $eventDispatcher);

    try {
        $useCase->execute('invalid-token', 'Password1!');
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVITATION_TOKEN_INVALID');
    }
});

test('throws INVITATION_TOKEN_EXPIRED for expired token', function () {
    $tenantUser = buildInvitedTenantUser();
    $pastDate = (new DateTimeImmutable)->modify('-1 hour');

    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $tenantUserRepo->shouldReceive('findByInvitationToken')
        ->with('expired-token')
        ->andReturn($tenantUser);
    $tenantUserRepo->shouldReceive('getInvitationExpiresAt')
        ->andReturn($pastDate);

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $passwordHasher = Mockery::mock(PasswordHasherInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ActivateResident($tenantUserRepo, $residentRepo, $passwordHasher, $eventDispatcher);

    try {
        $useCase->execute('expired-token', 'Password1!');
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVITATION_TOKEN_EXPIRED')
            ->and($e->context())->toHaveKey('expired_at');
    }
});

test('password is hashed via PasswordHasherInterface', function () {
    $tenantUser = buildInvitedTenantUser();
    $unitId = Uuid::generate();
    $resident = buildInvitedResident($unitId, $tenantUser->id());
    $resident->pullDomainEvents();
    $futureDate = (new DateTimeImmutable)->modify('+48 hours');

    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $tenantUserRepo->shouldReceive('findByInvitationToken')->andReturn($tenantUser);
    $tenantUserRepo->shouldReceive('getInvitationExpiresAt')->andReturn($futureDate);
    $tenantUserRepo->shouldReceive('save');
    $tenantUserRepo->shouldReceive('clearInvitationToken');

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findByTenantUserId')->andReturn([$resident]);
    $residentRepo->shouldReceive('save');

    $passwordHasher = Mockery::mock(PasswordHasherInterface::class);
    $passwordHasher->shouldReceive('hash')
        ->once()
        ->with('RawPassword123')
        ->andReturn('bcrypt-hashed-value');

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll');

    $useCase = new ActivateResident($tenantUserRepo, $residentRepo, $passwordHasher, $eventDispatcher);
    $useCase->execute('token-xyz', 'RawPassword123');

    // Verify the TenantUser received the hashed password
    expect($tenantUser->passwordHash())->toBe('bcrypt-hashed-value');
});

test('invitation token is cleared after activation', function () {
    $tenantUser = buildInvitedTenantUser();
    $unitId = Uuid::generate();
    $resident = buildInvitedResident($unitId, $tenantUser->id());
    $resident->pullDomainEvents();
    $futureDate = (new DateTimeImmutable)->modify('+48 hours');

    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $tenantUserRepo->shouldReceive('findByInvitationToken')->andReturn($tenantUser);
    $tenantUserRepo->shouldReceive('getInvitationExpiresAt')->andReturn($futureDate);
    $tenantUserRepo->shouldReceive('save');
    $tenantUserRepo->shouldReceive('clearInvitationToken')
        ->once()
        ->withArgs(fn (Uuid $id) => $id->equals($tenantUser->id()));

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findByTenantUserId')->andReturn([$resident]);
    $residentRepo->shouldReceive('save');

    $passwordHasher = Mockery::mock(PasswordHasherInterface::class);
    $passwordHasher->shouldReceive('hash')->andReturn('hashed');

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll');

    $useCase = new ActivateResident($tenantUserRepo, $residentRepo, $passwordHasher, $eventDispatcher);
    $useCase->execute('clear-token', 'Password1!');
});
