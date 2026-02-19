<?php

declare(strict_types=1);

namespace Application\Tenant\UseCases;

use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Tenant\Contracts\PendingRegistrationRepositoryInterface;
use Application\Tenant\Contracts\TenantRepositoryInterface;
use DateTimeImmutable;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Entities\Tenant;
use Domain\Tenant\Enums\CondominiumType;
use Domain\Tenant\Events\TenantCreated;

final readonly class VerifyRegistration
{
    public function __construct(
        private PendingRegistrationRepositoryInterface $pendingRegistrationRepository,
        private TenantRepositoryInterface $tenantRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(string $token): Tenant
    {
        $tokenHash = hash('sha256', $token);
        $pending = $this->pendingRegistrationRepository->findByTokenHash($tokenHash);

        if ($pending === null) {
            throw new DomainException(
                'Invalid or already used verification token',
                'VERIFICATION_TOKEN_INVALID',
            );
        }

        $expiresAt = new DateTimeImmutable($pending['expires_at']);
        if ($expiresAt < new DateTimeImmutable) {
            throw new DomainException(
                'Verification token has expired',
                'VERIFICATION_TOKEN_EXPIRED',
                ['expired_at' => $expiresAt->format('c')],
            );
        }

        $existingTenant = $this->tenantRepository->findBySlug($pending['slug']);
        if ($existingTenant !== null) {
            throw new DomainException(
                "Tenant with slug '{$pending['slug']}' already exists",
                'TENANT_SLUG_ALREADY_EXISTS',
                ['slug' => $pending['slug']],
            );
        }

        $pendingId = Uuid::fromString($pending['id']);
        $this->pendingRegistrationRepository->markVerified($pendingId);

        $tenantId = Uuid::generate();
        $type = CondominiumType::from($pending['type']);
        $tenant = Tenant::create($tenantId, $pending['slug'], $pending['name'], $type);
        $tenant->startProvisioning();

        $this->tenantRepository->save($tenant);

        $this->tenantRepository->saveConfig($tenantId, [
            'admin_name' => $pending['admin_name'],
            'admin_email' => $pending['admin_email'],
            'admin_password_hash' => $pending['admin_password_hash'],
            'admin_phone' => $pending['admin_phone'],
            'plan_slug' => $pending['plan_slug'],
        ]);

        $this->eventDispatcher->dispatch(new TenantCreated(
            tenantId: $tenantId,
            slug: $pending['slug'],
            name: $pending['name'],
            type: $pending['type'],
            occurredAt: new DateTimeImmutable,
        ));

        return $tenant;
    }
}
