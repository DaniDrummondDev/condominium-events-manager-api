<?php

declare(strict_types=1);

namespace Application\People\UseCases;

use Application\People\Contracts\ServiceProviderRepositoryInterface;
use Application\People\DTOs\ServiceProviderDTO;
use Application\People\DTOs\UpdateServiceProviderDTO;
use Domain\People\Enums\ServiceType;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class UpdateServiceProvider
{
    public function __construct(
        private ServiceProviderRepositoryInterface $serviceProviderRepository,
    ) {}

    public function execute(UpdateServiceProviderDTO $dto): ServiceProviderDTO
    {
        $provider = $this->serviceProviderRepository->findById(Uuid::fromString($dto->serviceProviderId));

        if ($provider === null) {
            throw new DomainException(
                'Service provider not found',
                'SERVICE_PROVIDER_NOT_FOUND',
                ['service_provider_id' => $dto->serviceProviderId],
            );
        }

        $provider->update(
            companyName: $dto->companyName,
            name: $dto->name,
            phone: $dto->phone,
            serviceType: ServiceType::from($dto->serviceType),
            notes: $dto->notes,
        );

        $this->serviceProviderRepository->save($provider);

        return RegisterServiceProvider::toDTO($provider);
    }
}
