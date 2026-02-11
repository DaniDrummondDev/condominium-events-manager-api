<?php

declare(strict_types=1);

namespace Application\Tenant\UseCases;

use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Tenant\Contracts\TenantRepositoryInterface;
use DateTimeImmutable;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Entities\Tenant;
use Domain\Tenant\Events\TenantSuspended;

final readonly class SuspendTenant
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(Uuid $tenantId, string $reason): Tenant
    {
        $tenant = $this->tenantRepository->findById($tenantId);

        if ($tenant === null) {
            throw new DomainException(
                'Tenant not found',
                'TENANT_NOT_FOUND',
                ['tenant_id' => $tenantId->value()],
            );
        }

        $tenant->suspend();
        $this->tenantRepository->save($tenant);

        $this->eventDispatcher->dispatch(new TenantSuspended(
            tenantId: $tenantId,
            reason: $reason,
            occurredAt: new DateTimeImmutable,
        ));

        return $tenant;
    }
}
