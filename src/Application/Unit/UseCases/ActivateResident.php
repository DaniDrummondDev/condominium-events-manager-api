<?php

declare(strict_types=1);

namespace Application\Unit\UseCases;

use Application\Auth\Contracts\TenantUserRepositoryInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Unit\Contracts\ResidentRepositoryInterface;
use DateTimeImmutable;
use Domain\Auth\Contracts\PasswordHasherInterface;
use Domain\Shared\Exceptions\DomainException;

final readonly class ActivateResident
{
    public function __construct(
        private TenantUserRepositoryInterface $tenantUserRepository,
        private ResidentRepositoryInterface $residentRepository,
        private PasswordHasherInterface $passwordHasher,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(string $token, string $password): void
    {
        $tenantUser = $this->tenantUserRepository->findByInvitationToken($token);

        if ($tenantUser === null) {
            throw new DomainException(
                'Invalid invitation token',
                'INVITATION_TOKEN_INVALID',
            );
        }

        $expiresAt = $this->tenantUserRepository->getInvitationExpiresAt($tenantUser->id());

        if ($expiresAt !== null && $expiresAt < new DateTimeImmutable) {
            throw new DomainException(
                'Invitation token has expired',
                'INVITATION_TOKEN_EXPIRED',
                ['expired_at' => $expiresAt->format('c')],
            );
        }

        $tenantUser->changePassword($this->passwordHasher->hash($password));
        $tenantUser->activate();
        $this->tenantUserRepository->save($tenantUser);
        $this->tenantUserRepository->clearInvitationToken($tenantUser->id());

        $residents = $this->residentRepository->findByTenantUserId($tenantUser->id());

        foreach ($residents as $resident) {
            $resident->activate();
            $this->residentRepository->save($resident);
            $this->eventDispatcher->dispatchAll($resident->pullDomainEvents());
        }
    }
}
