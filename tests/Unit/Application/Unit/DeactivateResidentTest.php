<?php

declare(strict_types=1);

use Application\Auth\Contracts\TenantUserRepositoryInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Unit\Contracts\ResidentRepositoryInterface;
use Application\Unit\UseCases\DeactivateResident;
use Domain\Auth\Entities\TenantUser;
use Domain\Auth\Enums\TenantRole;
use Domain\Auth\Enums\TenantUserStatus;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Resident;
use Domain\Unit\Enums\ResidentRole;
use Domain\Unit\Events\ResidentMovedOut;

afterEach(fn () => Mockery::close());

function buildActiveResident(?Uuid $unitId = null, ?Uuid $tenantUserId = null): Resident
{
    return Resident::create(
        Uuid::generate(),
        $unitId ?? Uuid::generate(),
        $tenantUserId ?? Uuid::generate(),
        'Morador Ativo',
        'morador@test.com',
        '11999990000',
        ResidentRole::Owner,
        true,
    );
}

function buildActiveTenantUser(?Uuid $id = null): TenantUser
{
    return new TenantUser(
        id: $id ?? Uuid::generate(),
        email: 'morador@test.com',
        name: 'Morador Ativo',
        passwordHash: 'hashed',
        role: TenantRole::Condomino,
        status: TenantUserStatus::Active,
    );
}

test('moves out resident successfully', function () {
    $tenantUserId = Uuid::generate();
    $resident = buildActiveResident(tenantUserId: $tenantUserId);
    $residentId = $resident->id();

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn($resident);
    $residentRepo->shouldReceive('save')
        ->once()
        ->withArgs(fn (Resident $r) => $r->status()->value === 'inactive'
            && $r->movedOutAt() !== null,
        );
    $residentRepo->shouldReceive('findByTenantUserId')->andReturn([$resident]);

    $tenantUser = buildActiveTenantUser($tenantUserId);
    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $tenantUserRepo->shouldReceive('findById')->andReturn($tenantUser);
    $tenantUserRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(function (array $events) {
            return count($events) === 1 && $events[0] instanceof ResidentMovedOut;
        });

    $useCase = new DeactivateResident($residentRepo, $tenantUserRepo, $eventDispatcher);
    $useCase->execute($residentId->value());
});

test('deactivates TenantUser when no other active resident exists', function () {
    $tenantUserId = Uuid::generate();
    $resident = buildActiveResident(tenantUserId: $tenantUserId);
    $residentId = $resident->id();

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn($resident);
    $residentRepo->shouldReceive('save');
    // After moveOut, this resident is the only one and it is now inactive
    $residentRepo->shouldReceive('findByTenantUserId')->andReturn([$resident]);

    $tenantUser = buildActiveTenantUser($tenantUserId);
    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $tenantUserRepo->shouldReceive('findById')->andReturn($tenantUser);
    $tenantUserRepo->shouldReceive('save')
        ->once()
        ->withArgs(fn (TenantUser $u) => $u->status() === TenantUserStatus::Inactive);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll');

    $useCase = new DeactivateResident($residentRepo, $tenantUserRepo, $eventDispatcher);
    $useCase->execute($residentId->value());

    expect($tenantUser->status())->toBe(TenantUserStatus::Inactive);
});

test('does NOT deactivate TenantUser when other active residents exist', function () {
    $tenantUserId = Uuid::generate();
    $unitId = Uuid::generate();

    $resident = buildActiveResident(unitId: $unitId, tenantUserId: $tenantUserId);
    $residentId = $resident->id();

    // Another active resident with the same tenantUserId but different unit
    $otherResident = Resident::create(
        Uuid::generate(),
        Uuid::generate(),
        $tenantUserId,
        'Morador Ativo',
        'morador@test.com',
        null,
        ResidentRole::TenantResident,
        false,
    );

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn($resident);
    $residentRepo->shouldReceive('save');
    // Returns both residents: the one being deactivated (now inactive) and the other (still active)
    $residentRepo->shouldReceive('findByTenantUserId')->andReturn([$resident, $otherResident]);

    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    // findById and save should NOT be called because there is another active resident
    $tenantUserRepo->shouldNotReceive('findById');
    $tenantUserRepo->shouldNotReceive('save');

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll');

    $useCase = new DeactivateResident($residentRepo, $tenantUserRepo, $eventDispatcher);
    $useCase->execute($residentId->value());
});

test('throws RESIDENT_NOT_FOUND when resident does not exist', function () {
    $residentId = Uuid::generate();

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturnNull();

    $tenantUserRepo = Mockery::mock(TenantUserRepositoryInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new DeactivateResident($residentRepo, $tenantUserRepo, $eventDispatcher);

    try {
        $useCase->execute($residentId->value());
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('RESIDENT_NOT_FOUND')
            ->and($e->context())->toHaveKey('resident_id', $residentId->value());
    }
});
