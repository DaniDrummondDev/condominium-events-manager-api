<?php

declare(strict_types=1);

use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Unit\Contracts\ResidentRepositoryInterface;
use Application\Unit\Contracts\UnitRepositoryInterface;
use Application\Unit\UseCases\DeactivateUnit;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Resident;
use Domain\Unit\Entities\Unit;
use Domain\Unit\Enums\ResidentRole;
use Domain\Unit\Enums\UnitType;
use Domain\Unit\Events\ResidentMovedOut;
use Domain\Unit\Events\UnitDeactivated;

afterEach(fn () => Mockery::close());

test('deactivates unit and marks vacant', function () {
    $unitId = Uuid::generate();
    $unit = Unit::create($unitId, null, 'Casa 1', null, UnitType::House);
    // Pull creation events so they do not interfere with assertion
    $unit->pullDomainEvents();

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn($unit);
    $unitRepo->shouldReceive('save')
        ->once()
        ->withArgs(fn (Unit $u) => $u->status()->value === 'inactive'
            && $u->isOccupied() === false,
        );

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findActiveByUnitId')->andReturn([]);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(function (array $events) {
            return count($events) === 1 && $events[0] instanceof UnitDeactivated;
        });

    $useCase = new DeactivateUnit($unitRepo, $residentRepo, $eventDispatcher);
    $useCase->execute($unitId->value());
});

test('cascades deactivation to active residents', function () {
    $unitId = Uuid::generate();
    $unit = Unit::create($unitId, null, 'Casa 1', null, UnitType::House);
    $unit->pullDomainEvents();

    $tenantUserId1 = Uuid::generate();
    $tenantUserId2 = Uuid::generate();

    $resident1 = Resident::create(
        Uuid::generate(), $unitId, $tenantUserId1,
        'Maria', 'maria@test.com', null,
        ResidentRole::Owner, true,
    );

    $resident2 = Resident::create(
        Uuid::generate(), $unitId, $tenantUserId2,
        'Joao', 'joao@test.com', null,
        ResidentRole::Dependent, false,
    );

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn($unit);
    $unitRepo->shouldReceive('save')->once();

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findActiveByUnitId')->andReturn([$resident1, $resident2]);
    $residentRepo->shouldReceive('save')->twice();

    $dispatchedEvents = [];
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->times(3) // 2 residents + 1 unit
        ->withArgs(function (array $events) use (&$dispatchedEvents) {
            $dispatchedEvents = array_merge($dispatchedEvents, $events);

            return true;
        });

    $useCase = new DeactivateUnit($unitRepo, $residentRepo, $eventDispatcher);
    $useCase->execute($unitId->value());

    $movedOutEvents = array_filter($dispatchedEvents, fn ($e) => $e instanceof ResidentMovedOut);
    $deactivatedEvents = array_filter($dispatchedEvents, fn ($e) => $e instanceof UnitDeactivated);

    expect(count($movedOutEvents))->toBe(2)
        ->and(count($deactivatedEvents))->toBe(1);
});

test('throws UNIT_NOT_FOUND when unit does not exist', function () {
    $unitId = Uuid::generate();

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturnNull();

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new DeactivateUnit($unitRepo, $residentRepo, $eventDispatcher);

    try {
        $useCase->execute($unitId->value());
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('UNIT_NOT_FOUND')
            ->and($e->context())->toHaveKey('unit_id', $unitId->value());
    }
});

test('events dispatched for unit and each resident', function () {
    $unitId = Uuid::generate();
    $unit = Unit::create($unitId, null, 'Casa 5', null, UnitType::House);
    $unit->pullDomainEvents();

    $resident = Resident::create(
        Uuid::generate(), $unitId, Uuid::generate(),
        'Carlos', 'carlos@test.com', '11999990000',
        ResidentRole::TenantResident, false,
    );

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn($unit);
    $unitRepo->shouldReceive('save')->once();

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findActiveByUnitId')->andReturn([$resident]);
    $residentRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    // dispatchAll called once for the resident, once for the unit
    $eventDispatcher->shouldReceive('dispatchAll')->twice();

    $useCase = new DeactivateUnit($unitRepo, $residentRepo, $eventDispatcher);
    $useCase->execute($unitId->value());
});
