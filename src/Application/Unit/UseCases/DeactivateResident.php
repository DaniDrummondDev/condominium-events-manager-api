<?php

declare(strict_types=1);

namespace Application\Unit\UseCases;

use Application\Auth\Contracts\TenantUserRepositoryInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Unit\Contracts\ResidentRepositoryInterface;
use DateTimeImmutable;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class DeactivateResident
{
    public function __construct(
        private ResidentRepositoryInterface $residentRepository,
        private TenantUserRepositoryInterface $tenantUserRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(string $residentId): void
    {
        $id = Uuid::fromString($residentId);
        $resident = $this->residentRepository->findById($id);

        if ($resident === null) {
            throw new DomainException(
                'Resident not found',
                'RESIDENT_NOT_FOUND',
                ['resident_id' => $residentId],
            );
        }

        $resident->moveOut(new DateTimeImmutable);
        $this->residentRepository->save($resident);
        $this->eventDispatcher->dispatchAll($resident->pullDomainEvents());

        $otherResidents = $this->residentRepository->findByTenantUserId($resident->tenantUserId());
        $hasOtherActive = false;

        foreach ($otherResidents as $other) {
            if ($other->id()->value() !== $residentId && $other->isActive()) {
                $hasOtherActive = true;
                break;
            }
        }

        if (! $hasOtherActive) {
            $tenantUser = $this->tenantUserRepository->findById($resident->tenantUserId());

            if ($tenantUser !== null) {
                $tenantUser->deactivate();
                $this->tenantUserRepository->save($tenantUser);
            }
        }
    }
}
