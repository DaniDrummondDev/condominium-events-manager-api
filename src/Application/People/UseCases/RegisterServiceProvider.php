<?php

declare(strict_types=1);

namespace Application\People\UseCases;

use Application\People\Contracts\ServiceProviderRepositoryInterface;
use Application\People\DTOs\RegisterServiceProviderDTO;
use Application\People\DTOs\ServiceProviderDTO;
use Domain\People\Entities\ServiceProvider;
use Domain\People\Enums\ServiceType;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class RegisterServiceProvider
{
    public function __construct(
        private ServiceProviderRepositoryInterface $serviceProviderRepository,
    ) {}

    public function execute(RegisterServiceProviderDTO $dto): ServiceProviderDTO
    {
        $existing = $this->serviceProviderRepository->findByDocument($dto->document);

        if ($existing !== null) {
            throw new DomainException(
                'A service provider with this document already exists',
                'DUPLICATE_DOCUMENT',
                ['document' => $dto->document],
            );
        }

        $provider = ServiceProvider::create(
            id: Uuid::generate(),
            companyName: $dto->companyName,
            name: $dto->name,
            document: $dto->document,
            phone: $dto->phone,
            serviceType: ServiceType::from($dto->serviceType),
            notes: $dto->notes,
            createdBy: Uuid::fromString($dto->createdBy),
        );

        $this->serviceProviderRepository->save($provider);

        return self::toDTO($provider);
    }

    public static function toDTO(ServiceProvider $provider): ServiceProviderDTO
    {
        return new ServiceProviderDTO(
            id: $provider->id()->value(),
            companyName: $provider->companyName(),
            name: $provider->name(),
            document: $provider->document(),
            phone: $provider->phone(),
            serviceType: $provider->serviceType()->value,
            status: $provider->status()->value,
            notes: $provider->notes(),
            createdBy: $provider->createdBy()->value(),
            createdAt: $provider->createdAt()->format('c'),
        );
    }
}
