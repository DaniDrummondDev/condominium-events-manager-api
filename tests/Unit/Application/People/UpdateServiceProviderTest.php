<?php

declare(strict_types=1);

use Application\People\Contracts\ServiceProviderRepositoryInterface;
use Application\People\DTOs\ServiceProviderDTO;
use Application\People\DTOs\UpdateServiceProviderDTO;
use Application\People\UseCases\UpdateServiceProvider;
use Domain\People\Entities\ServiceProvider;
use Domain\People\Enums\ServiceType;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

test('updates an existing service provider', function () {
    $provider = ServiceProvider::create(
        Uuid::generate(),
        'Old Company',
        'Old Name',
        '12345678000199',
        '11999999999',
        ServiceType::Buffet,
        null,
        Uuid::generate(),
    );

    $dto = new UpdateServiceProviderDTO(
        serviceProviderId: $provider->id()->value(),
        companyName: 'New Company',
        name: 'New Name',
        phone: '11888888888',
        serviceType: 'security',
        notes: 'Updated notes',
    );

    $providerRepo = Mockery::mock(ServiceProviderRepositoryInterface::class);
    $providerRepo->shouldReceive('findById')->once()->andReturn($provider);
    $providerRepo->shouldReceive('save')->once();

    $useCase = new UpdateServiceProvider($providerRepo);
    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(ServiceProviderDTO::class)
        ->and($result->companyName)->toBe('New Company')
        ->and($result->name)->toBe('New Name')
        ->and($result->phone)->toBe('11888888888')
        ->and($result->serviceType)->toBe('security')
        ->and($result->notes)->toBe('Updated notes');
});

test('throws SERVICE_PROVIDER_NOT_FOUND when provider does not exist', function () {
    $dto = new UpdateServiceProviderDTO(
        serviceProviderId: Uuid::generate()->value(),
        companyName: 'X',
        name: 'Y',
        phone: null,
        serviceType: 'other',
        notes: null,
    );

    $providerRepo = Mockery::mock(ServiceProviderRepositoryInterface::class);
    $providerRepo->shouldReceive('findById')->andReturnNull();

    $useCase = new UpdateServiceProvider($providerRepo);

    $useCase->execute($dto);
})->throws(DomainException::class, 'Service provider not found');
