<?php

declare(strict_types=1);

namespace Application\People\Contracts;

use Domain\People\Entities\ServiceProvider;
use Domain\Shared\ValueObjects\Uuid;

interface ServiceProviderRepositoryInterface
{
    public function findById(Uuid $id): ?ServiceProvider;

    /**
     * @return array<ServiceProvider>
     */
    public function findAll(): array;

    public function findByDocument(string $document): ?ServiceProvider;

    public function save(ServiceProvider $provider): void;
}
