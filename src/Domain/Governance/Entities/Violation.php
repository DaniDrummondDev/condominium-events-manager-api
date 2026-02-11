<?php

declare(strict_types=1);

namespace Domain\Governance\Entities;

use DateTimeImmutable;
use Domain\Governance\Enums\ViolationSeverity;
use Domain\Governance\Enums\ViolationStatus;
use Domain\Governance\Enums\ViolationType;
use Domain\Governance\Events\ViolationContested;
use Domain\Governance\Events\ViolationRegistered;
use Domain\Governance\Events\ViolationRevoked;
use Domain\Governance\Events\ViolationUpheld;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

class Violation
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $unitId,
        private readonly ?Uuid $tenantUserId,
        private readonly ?Uuid $reservationId,
        private readonly ?Uuid $ruleId,
        private readonly ViolationType $type,
        private readonly ViolationSeverity $severity,
        private readonly string $description,
        private ViolationStatus $status,
        private readonly bool $isAutomatic,
        private readonly ?Uuid $createdBy,
        private ?Uuid $upheldBy,
        private ?DateTimeImmutable $upheldAt,
        private ?Uuid $revokedBy,
        private ?DateTimeImmutable $revokedAt,
        private ?string $revokedReason,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $unitId,
        ?Uuid $tenantUserId,
        ?Uuid $reservationId,
        ?Uuid $ruleId,
        ViolationType $type,
        ViolationSeverity $severity,
        string $description,
        Uuid $createdBy,
    ): self {
        $violation = new self(
            id: $id,
            unitId: $unitId,
            tenantUserId: $tenantUserId,
            reservationId: $reservationId,
            ruleId: $ruleId,
            type: $type,
            severity: $severity,
            description: $description,
            status: ViolationStatus::Open,
            isAutomatic: false,
            createdBy: $createdBy,
            upheldBy: null,
            upheldAt: null,
            revokedBy: null,
            revokedAt: null,
            revokedReason: null,
            createdAt: new DateTimeImmutable,
        );

        $violation->domainEvents[] = new ViolationRegistered(
            violationId: $id->value(),
            unitId: $unitId->value(),
            type: $type->value,
            severity: $severity->value,
            isAutomatic: false,
        );

        return $violation;
    }

    public static function createAutomatic(
        Uuid $id,
        Uuid $unitId,
        ?Uuid $tenantUserId,
        ?Uuid $reservationId,
        ViolationType $type,
        ViolationSeverity $severity,
        string $description,
    ): self {
        $violation = new self(
            id: $id,
            unitId: $unitId,
            tenantUserId: $tenantUserId,
            reservationId: $reservationId,
            ruleId: null,
            type: $type,
            severity: $severity,
            description: $description,
            status: ViolationStatus::Open,
            isAutomatic: true,
            createdBy: null,
            upheldBy: null,
            upheldAt: null,
            revokedBy: null,
            revokedAt: null,
            revokedReason: null,
            createdAt: new DateTimeImmutable,
        );

        $violation->domainEvents[] = new ViolationRegistered(
            violationId: $id->value(),
            unitId: $unitId->value(),
            type: $type->value,
            severity: $severity->value,
            isAutomatic: true,
        );

        return $violation;
    }

    public function uphold(Uuid $upheldBy): void
    {
        $this->transitionTo(ViolationStatus::Upheld);

        $this->upheldBy = $upheldBy;
        $this->upheldAt = new DateTimeImmutable;

        $this->domainEvents[] = new ViolationUpheld(
            violationId: $this->id->value(),
            unitId: $this->unitId->value(),
            upheldBy: $upheldBy->value(),
        );
    }

    public function revoke(Uuid $revokedBy, string $reason): void
    {
        $this->transitionTo(ViolationStatus::Revoked);

        $this->revokedBy = $revokedBy;
        $this->revokedAt = new DateTimeImmutable;
        $this->revokedReason = $reason;

        $this->domainEvents[] = new ViolationRevoked(
            violationId: $this->id->value(),
            unitId: $this->unitId->value(),
            revokedBy: $revokedBy->value(),
            reason: $reason,
        );
    }

    public function contest(): void
    {
        $this->transitionTo(ViolationStatus::Contested);

        $this->domainEvents[] = new ViolationContested(
            violationId: $this->id->value(),
            unitId: $this->unitId->value(),
        );
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function unitId(): Uuid
    {
        return $this->unitId;
    }

    public function tenantUserId(): ?Uuid
    {
        return $this->tenantUserId;
    }

    public function reservationId(): ?Uuid
    {
        return $this->reservationId;
    }

    public function ruleId(): ?Uuid
    {
        return $this->ruleId;
    }

    public function type(): ViolationType
    {
        return $this->type;
    }

    public function severity(): ViolationSeverity
    {
        return $this->severity;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function status(): ViolationStatus
    {
        return $this->status;
    }

    public function isAutomatic(): bool
    {
        return $this->isAutomatic;
    }

    public function createdBy(): ?Uuid
    {
        return $this->createdBy;
    }

    public function upheldBy(): ?Uuid
    {
        return $this->upheldBy;
    }

    public function upheldAt(): ?DateTimeImmutable
    {
        return $this->upheldAt;
    }

    public function revokedBy(): ?Uuid
    {
        return $this->revokedBy;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revokedReason(): ?string
    {
        return $this->revokedReason;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
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

    private function transitionTo(ViolationStatus $newStatus): void
    {
        if (! $this->status->canTransitionTo($newStatus)) {
            throw DomainException::businessRule(
                'INVALID_STATUS_TRANSITION',
                "Cannot transition from {$this->status->value} to {$newStatus->value}",
                [
                    'violation_id' => $this->id->value(),
                    'current_status' => $this->status->value,
                    'new_status' => $newStatus->value,
                ],
            );
        }

        $this->status = $newStatus;
    }
}
