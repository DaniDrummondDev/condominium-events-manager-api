<?php

declare(strict_types=1);

namespace Application\Tenant\UseCases;

use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Tenant\Contracts\TenantRepositoryInterface;
use Application\Tenant\DTOs\CreateTenantDTO;
use DateTimeImmutable;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Entities\Tenant;
use Domain\Tenant\Enums\CondominiumType;
use Domain\Tenant\Events\TenantCreated;

final readonly class ProvisionTenant
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(CreateTenantDTO $dto): Tenant
    {
        $existing = $this->tenantRepository->findBySlug($dto->slug);
        if ($existing !== null) {
            throw new DomainException(
                "Tenant with slug '{$dto->slug}' already exists",
                'TENANT_SLUG_ALREADY_EXISTS',
                ['slug' => $dto->slug],
            );
        }

        $type = CondominiumType::tryFrom($dto->type);
        if ($type === null) {
            throw new DomainException(
                "Invalid condominium type: '{$dto->type}'",
                'INVALID_CONDOMINIUM_TYPE',
                ['type' => $dto->type, 'allowed' => ['horizontal', 'vertical', 'mixed']],
            );
        }

        $id = Uuid::generate();
        $tenant = Tenant::create($id, $dto->slug, $dto->name, $type);
        $tenant->startProvisioning();

        $this->tenantRepository->save($tenant);

        $this->eventDispatcher->dispatch(new TenantCreated(
            tenantId: $id,
            slug: $dto->slug,
            name: $dto->name,
            type: $dto->type,
            occurredAt: new DateTimeImmutable,
        ));

        return $tenant;
    }
}
