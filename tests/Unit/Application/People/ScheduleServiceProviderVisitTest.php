<?php

declare(strict_types=1);

use Application\People\Contracts\ServiceProviderRepositoryInterface;
use Application\People\Contracts\ServiceProviderVisitRepositoryInterface;
use Application\People\DTOs\ScheduleVisitDTO;
use Application\People\DTOs\ServiceProviderVisitDTO;
use Application\People\UseCases\ScheduleServiceProviderVisit;
use Application\Unit\Contracts\UnitRepositoryInterface;
use Domain\People\Entities\ServiceProvider;
use Domain\People\Enums\ServiceType;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Unit;

afterEach(fn () => Mockery::close());

function makeScheduleVisitDTO(string $providerId, string $unitId): ScheduleVisitDTO
{
    return new ScheduleVisitDTO(
        serviceProviderId: $providerId,
        unitId: $unitId,
        reservationId: null,
        scheduledDate: (new DateTimeImmutable('+1 day'))->format('Y-m-d'),
        purpose: 'Manutenção do elevador',
        notes: null,
        createdBy: Uuid::generate()->value(),
    );
}

test('schedules a visit for active provider and active unit', function () {
    $provider = ServiceProvider::create(
        Uuid::generate(),
        'Empresa X',
        'Carlos',
        '12345678000199',
        null,
        ServiceType::Maintenance,
        null,
        Uuid::generate(),
    );

    $unit = Mockery::mock(Unit::class);
    $unit->shouldReceive('isActive')->andReturnTrue();

    $dto = makeScheduleVisitDTO($provider->id()->value(), Uuid::generate()->value());

    $visitRepo = Mockery::mock(ServiceProviderVisitRepositoryInterface::class);
    $visitRepo->shouldReceive('save')->once();

    $providerRepo = Mockery::mock(ServiceProviderRepositoryInterface::class);
    $providerRepo->shouldReceive('findById')->once()->andReturn($provider);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->once()->andReturn($unit);

    $useCase = new ScheduleServiceProviderVisit($visitRepo, $providerRepo, $unitRepo);
    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(ServiceProviderVisitDTO::class)
        ->and($result->status)->toBe('scheduled')
        ->and($result->purpose)->toBe('Manutenção do elevador');
});

test('throws SERVICE_PROVIDER_NOT_FOUND when provider does not exist', function () {
    $dto = makeScheduleVisitDTO(Uuid::generate()->value(), Uuid::generate()->value());

    $visitRepo = Mockery::mock(ServiceProviderVisitRepositoryInterface::class);
    $providerRepo = Mockery::mock(ServiceProviderRepositoryInterface::class);
    $providerRepo->shouldReceive('findById')->andReturnNull();

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);

    $useCase = new ScheduleServiceProviderVisit($visitRepo, $providerRepo, $unitRepo);

    $useCase->execute($dto);
})->throws(DomainException::class, 'Service provider not found');

test('throws PROVIDER_NOT_ACTIVE when provider is inactive', function () {
    $provider = ServiceProvider::create(
        Uuid::generate(),
        null,
        'Carlos',
        '12345678000199',
        null,
        ServiceType::Maintenance,
        null,
        Uuid::generate(),
    );
    $provider->deactivate();

    $dto = makeScheduleVisitDTO($provider->id()->value(), Uuid::generate()->value());

    $visitRepo = Mockery::mock(ServiceProviderVisitRepositoryInterface::class);
    $providerRepo = Mockery::mock(ServiceProviderRepositoryInterface::class);
    $providerRepo->shouldReceive('findById')->andReturn($provider);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);

    $useCase = new ScheduleServiceProviderVisit($visitRepo, $providerRepo, $unitRepo);

    $useCase->execute($dto);
})->throws(DomainException::class, 'Service provider is not active');

test('throws UNIT_NOT_FOUND when unit does not exist', function () {
    $provider = ServiceProvider::create(
        Uuid::generate(),
        null,
        'Carlos',
        '12345678000199',
        null,
        ServiceType::Maintenance,
        null,
        Uuid::generate(),
    );

    $dto = makeScheduleVisitDTO($provider->id()->value(), Uuid::generate()->value());

    $visitRepo = Mockery::mock(ServiceProviderVisitRepositoryInterface::class);
    $providerRepo = Mockery::mock(ServiceProviderRepositoryInterface::class);
    $providerRepo->shouldReceive('findById')->andReturn($provider);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturnNull();

    $useCase = new ScheduleServiceProviderVisit($visitRepo, $providerRepo, $unitRepo);

    $useCase->execute($dto);
})->throws(DomainException::class, 'Unit not found');

test('throws UNIT_NOT_ACTIVE when unit is inactive', function () {
    $provider = ServiceProvider::create(
        Uuid::generate(),
        null,
        'Carlos',
        '12345678000199',
        null,
        ServiceType::Maintenance,
        null,
        Uuid::generate(),
    );

    $unit = Mockery::mock(Unit::class);
    $unit->shouldReceive('isActive')->andReturnFalse();

    $dto = makeScheduleVisitDTO($provider->id()->value(), Uuid::generate()->value());

    $visitRepo = Mockery::mock(ServiceProviderVisitRepositoryInterface::class);
    $providerRepo = Mockery::mock(ServiceProviderRepositoryInterface::class);
    $providerRepo->shouldReceive('findById')->andReturn($provider);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn($unit);

    $useCase = new ScheduleServiceProviderVisit($visitRepo, $providerRepo, $unitRepo);

    $useCase->execute($dto);
})->throws(DomainException::class, 'Unit is not active');
