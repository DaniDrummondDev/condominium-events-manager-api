<?php

declare(strict_types=1);

use Application\People\Contracts\ServiceProviderRepositoryInterface;
use Application\People\DTOs\RegisterServiceProviderDTO;
use Application\People\DTOs\ServiceProviderDTO;
use Application\People\UseCases\RegisterServiceProvider;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function makeRegisterProviderDTO(): RegisterServiceProviderDTO
{
    return new RegisterServiceProviderDTO(
        companyName: 'Buffet Gourmet',
        name: 'Carlos Souza',
        document: '12345678000199',
        phone: '11999999999',
        serviceType: 'buffet',
        notes: null,
        createdBy: Uuid::generate()->value(),
    );
}

test('registers a new service provider', function () {
    $dto = makeRegisterProviderDTO();

    $providerRepo = Mockery::mock(ServiceProviderRepositoryInterface::class);
    $providerRepo->shouldReceive('findByDocument')->once()->andReturnNull();
    $providerRepo->shouldReceive('save')->once();

    $useCase = new RegisterServiceProvider($providerRepo);
    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(ServiceProviderDTO::class)
        ->and($result->name)->toBe('Carlos Souza')
        ->and($result->document)->toBe('12345678000199')
        ->and($result->status)->toBe('active')
        ->and($result->serviceType)->toBe('buffet');
});

test('throws DUPLICATE_DOCUMENT when document already exists', function () {
    $dto = makeRegisterProviderDTO();

    $existing = \Domain\People\Entities\ServiceProvider::create(
        Uuid::generate(),
        'Existing',
        'Existing',
        '12345678000199',
        null,
        \Domain\People\Enums\ServiceType::Buffet,
        null,
        Uuid::generate(),
    );

    $providerRepo = Mockery::mock(ServiceProviderRepositoryInterface::class);
    $providerRepo->shouldReceive('findByDocument')->andReturn($existing);

    $useCase = new RegisterServiceProvider($providerRepo);

    $useCase->execute($dto);
})->throws(DomainException::class, 'A service provider with this document already exists');
