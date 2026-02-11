<?php

declare(strict_types=1);

namespace Domain\Unit\Entities;

use DateTimeImmutable;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Enums\ResidentRole;
use Domain\Unit\Enums\ResidentStatus;
use Domain\Unit\Events\ResidentActivated;
use Domain\Unit\Events\ResidentDeactivated;
use Domain\Unit\Events\ResidentInvited;
use Domain\Unit\Events\ResidentMovedOut;

class Resident
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $unitId,
        private readonly Uuid $tenantUserId,
        private readonly string $name,
        private readonly string $email,
        private readonly ?string $phone,
        private readonly ResidentRole $roleInUnit,
        private readonly bool $isPrimary,
        private ResidentStatus $status,
        private readonly DateTimeImmutable $movedInAt,
        private ?DateTimeImmutable $movedOutAt,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $unitId,
        Uuid $tenantUserId,
        string $name,
        string $email,
        ?string $phone,
        ResidentRole $roleInUnit,
        bool $isPrimary,
    ): self {
        $now = new DateTimeImmutable;

        return new self(
            $id, $unitId, $tenantUserId, $name, $email, $phone,
            $roleInUnit, $isPrimary, ResidentStatus::Active, $now, null, $now,
        );
    }

    public static function createInvited(
        Uuid $id,
        Uuid $unitId,
        Uuid $tenantUserId,
        string $name,
        string $email,
        ?string $phone,
        ResidentRole $roleInUnit,
        bool $isPrimary,
    ): self {
        $now = new DateTimeImmutable;

        $resident = new self(
            $id, $unitId, $tenantUserId, $name, $email, $phone,
            $roleInUnit, $isPrimary, ResidentStatus::Invited, $now, null, $now,
        );

        $resident->domainEvents[] = new ResidentInvited(
            $id->value(),
            $unitId->value(),
            $email,
        );

        return $resident;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function unitId(): Uuid
    {
        return $this->unitId;
    }

    public function tenantUserId(): Uuid
    {
        return $this->tenantUserId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function roleInUnit(): ResidentRole
    {
        return $this->roleInUnit;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function status(): ResidentStatus
    {
        return $this->status;
    }

    public function movedInAt(): DateTimeImmutable
    {
        return $this->movedInAt;
    }

    public function movedOutAt(): ?DateTimeImmutable
    {
        return $this->movedOutAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function activate(): void
    {
        if ($this->status === ResidentStatus::Active) {
            return;
        }

        if ($this->status !== ResidentStatus::Invited) {
            throw new DomainException(
                "Cannot activate resident with status '{$this->status->value}'",
                'RESIDENT_INVALID_STATUS',
                ['resident_id' => $this->id->value(), 'status' => $this->status->value],
            );
        }

        $this->status = ResidentStatus::Active;
        $this->domainEvents[] = new ResidentActivated($this->id->value(), $this->tenantUserId->value());
    }

    public function deactivate(): void
    {
        if ($this->status === ResidentStatus::Inactive) {
            return;
        }

        $this->status = ResidentStatus::Inactive;
        $this->movedOutAt = new DateTimeImmutable;
        $this->domainEvents[] = new ResidentDeactivated($this->id->value(), $this->unitId->value());
    }

    public function moveOut(DateTimeImmutable $movedOutAt): void
    {
        if ($this->movedOutAt !== null) {
            throw new DomainException(
                'Resident has already moved out',
                'RESIDENT_ALREADY_MOVED_OUT',
                ['resident_id' => $this->id->value()],
            );
        }

        $this->movedOutAt = $movedOutAt;
        $this->status = ResidentStatus::Inactive;
        $this->domainEvents[] = new ResidentMovedOut(
            $this->id->value(),
            $this->unitId->value(),
            $movedOutAt->format('c'),
        );
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * @return array<object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
