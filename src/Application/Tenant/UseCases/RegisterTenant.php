<?php

declare(strict_types=1);

namespace Application\Tenant\UseCases;

use Application\Billing\Contracts\PlanRepositoryInterface;
use Application\Shared\Contracts\NotificationServiceInterface;
use Application\Tenant\Contracts\PendingRegistrationRepositoryInterface;
use Application\Tenant\Contracts\TenantRepositoryInterface;
use Application\Tenant\DTOs\PendingRegistrationDTO;
use Application\Tenant\DTOs\RegisterTenantDTO;
use DateTimeImmutable;
use Domain\Auth\Contracts\PasswordHasherInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Enums\CondominiumType;
use Illuminate\Support\Str;

final readonly class RegisterTenant
{
    private const int TOKEN_LENGTH = 64;

    private const int TOKEN_EXPIRY_HOURS = 24;

    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private PendingRegistrationRepositoryInterface $pendingRegistrationRepository,
        private PlanRepositoryInterface $planRepository,
        private PasswordHasherInterface $passwordHasher,
        private NotificationServiceInterface $notificationService,
    ) {}

    public function execute(RegisterTenantDTO $dto): PendingRegistrationDTO
    {
        $existing = $this->tenantRepository->findBySlug($dto->slug);
        if ($existing !== null) {
            throw new DomainException(
                "Tenant with slug '{$dto->slug}' already exists",
                'TENANT_SLUG_ALREADY_EXISTS',
                ['slug' => $dto->slug],
            );
        }

        $pendingBySlug = $this->pendingRegistrationRepository->findActiveBySlug($dto->slug);
        if ($pendingBySlug !== null) {
            throw new DomainException(
                "A registration for slug '{$dto->slug}' is already pending verification",
                'REGISTRATION_SLUG_PENDING',
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

        $plan = $this->planRepository->findBySlug($dto->planSlug);
        if ($plan === null || ! $plan->status()->isAvailable()) {
            throw new DomainException(
                "Plan '{$dto->planSlug}' is not available",
                'PLAN_NOT_AVAILABLE',
                ['plan_slug' => $dto->planSlug],
            );
        }

        $id = Uuid::generate();
        $plainToken = Str::random(self::TOKEN_LENGTH);
        $tokenHash = hash('sha256', $plainToken);
        $passwordHash = $this->passwordHasher->hash($dto->adminPassword);
        $expiresAt = (new DateTimeImmutable)->modify('+'.self::TOKEN_EXPIRY_HOURS.' hours');

        $this->pendingRegistrationRepository->save(
            id: $id,
            slug: $dto->slug,
            name: $dto->name,
            type: $dto->type,
            adminName: $dto->adminName,
            adminEmail: $dto->adminEmail,
            adminPasswordHash: $passwordHash,
            adminPhone: $dto->adminPhone,
            planSlug: $dto->planSlug,
            verificationTokenHash: $tokenHash,
            expiresAt: $expiresAt,
        );

        $this->notificationService->send('email', $dto->adminEmail, 'tenant-verification', [
            'admin_name' => $dto->adminName,
            'condominium_name' => $dto->name,
            'verification_token' => $plainToken,
            'expires_at' => $expiresAt->format('d/m/Y H:i'),
        ]);

        return new PendingRegistrationDTO(
            id: $id->value(),
            slug: $dto->slug,
            name: $dto->name,
            type: $dto->type,
            adminName: $dto->adminName,
            adminEmail: $dto->adminEmail,
            planSlug: $dto->planSlug,
            expiresAt: $expiresAt->format('c'),
        );
    }
}
