<?php

declare(strict_types=1);

namespace Application\Unit\UseCases;

use App\Infrastructure\MultiTenancy\TenantContext;
use Application\Auth\Contracts\TenantUserRepositoryInterface;
use Application\Billing\Contracts\FeatureResolverInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Shared\Contracts\NotificationServiceInterface;
use Application\Unit\Contracts\ResidentRepositoryInterface;
use Application\Unit\Contracts\UnitRepositoryInterface;
use Application\Unit\DTOs\InviteResidentDTO;
use Application\Unit\DTOs\ResidentDTO;
use DateTimeImmutable;
use Domain\Auth\Entities\TenantUser;
use Domain\Auth\Enums\TenantRole;
use Domain\Auth\Enums\TenantUserStatus;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Resident;
use Domain\Unit\Enums\ResidentRole;
use Illuminate\Support\Str;

final readonly class InviteResident
{
    private const int TOKEN_LENGTH = 64;

    private const int TOKEN_EXPIRY_HOURS = 72;

    public function __construct(
        private UnitRepositoryInterface $unitRepository,
        private ResidentRepositoryInterface $residentRepository,
        private TenantUserRepositoryInterface $tenantUserRepository,
        private FeatureResolverInterface $featureResolver,
        private NotificationServiceInterface $notificationService,
        private EventDispatcherInterface $eventDispatcher,
        private TenantContext $tenantContext,
    ) {}

    public function execute(InviteResidentDTO $dto): ResidentDTO
    {
        $tenantId = Uuid::fromString($this->tenantContext->tenantId);
        $unitId = Uuid::fromString($dto->unitId);

        $unit = $this->unitRepository->findById($unitId);

        if ($unit === null) {
            throw new DomainException(
                'Unit not found',
                'UNIT_NOT_FOUND',
                ['unit_id' => $dto->unitId],
            );
        }

        if (! $unit->isActive()) {
            throw new DomainException(
                'Cannot invite resident to inactive unit',
                'UNIT_INACTIVE',
                ['unit_id' => $dto->unitId],
            );
        }

        $existingUser = $this->tenantUserRepository->findByEmail($dto->email);

        if ($existingUser !== null) {
            throw new DomainException(
                'A user with this email already exists in this condominium',
                'USER_EMAIL_DUPLICATE',
                ['email' => $dto->email],
            );
        }

        $maxUsers = $this->featureResolver->featureLimit($tenantId, 'max_users');

        if ($maxUsers > 0) {
            $currentUnits = $this->unitRepository->countByTenant();

            if ($currentUnits >= $maxUsers) {
                throw new DomainException(
                    "User limit reached ({$maxUsers})",
                    'USER_LIMIT_REACHED',
                    ['max_users' => $maxUsers],
                );
            }
        }

        $maxResidents = $this->featureResolver->featureLimit($tenantId, 'max_residents_per_unit');

        if ($maxResidents > 0) {
            $currentResidents = $this->residentRepository->countActiveByUnitId($unitId);

            if ($currentResidents >= $maxResidents) {
                throw new DomainException(
                    "Resident limit per unit reached ({$maxResidents})",
                    'RESIDENT_LIMIT_REACHED',
                    ['max_residents_per_unit' => $maxResidents, 'current_count' => $currentResidents],
                );
            }
        }

        $tenantUserId = Uuid::generate();
        $tenantUser = new TenantUser(
            id: $tenantUserId,
            email: $dto->email,
            name: $dto->name,
            passwordHash: '',
            role: TenantRole::Condomino,
            status: TenantUserStatus::Invited,
            phone: $dto->phone,
        );

        $this->tenantUserRepository->save($tenantUser);

        $token = Str::random(self::TOKEN_LENGTH);
        $expiresAt = (new DateTimeImmutable)->modify('+'.self::TOKEN_EXPIRY_HOURS.' hours');
        $this->tenantUserRepository->saveInvitationToken($tenantUserId, $token, $expiresAt);

        $roleInUnit = ResidentRole::from($dto->roleInUnit);
        $isPrimary = $roleInUnit === ResidentRole::Owner;

        $resident = Resident::createInvited(
            Uuid::generate(),
            $unitId,
            $tenantUserId,
            $dto->name,
            $dto->email,
            $dto->phone,
            $roleInUnit,
            $isPrimary,
        );

        $this->residentRepository->save($resident);
        $this->eventDispatcher->dispatchAll($resident->pullDomainEvents());

        $this->notificationService->send('email', $dto->email, 'resident-invitation', [
            'name' => $dto->name,
            'condominium_name' => $this->tenantContext->tenantName,
            'token' => $token,
            'expires_at' => $expiresAt->format('c'),
        ]);

        return new ResidentDTO(
            id: $resident->id()->value(),
            unitId: $resident->unitId()->value(),
            tenantUserId: $resident->tenantUserId()->value(),
            name: $resident->name(),
            email: $resident->email(),
            phone: $resident->phone(),
            roleInUnit: $resident->roleInUnit()->value,
            isPrimary: $resident->isPrimary(),
            status: $resident->status()->value,
            movedInAt: $resident->movedInAt()->format('c'),
            movedOutAt: null,
        );
    }
}
