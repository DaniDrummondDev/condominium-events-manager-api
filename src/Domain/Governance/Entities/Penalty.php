<?php

declare(strict_types=1);

namespace Domain\Governance\Entities;

use DateTimeImmutable;
use Domain\Governance\Enums\PenaltyStatus;
use Domain\Governance\Enums\PenaltyType;
use Domain\Governance\Events\PenaltyApplied;
use Domain\Governance\Events\PenaltyExpired;
use Domain\Governance\Events\PenaltyRevoked;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

class Penalty
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $violationId,
        private readonly Uuid $unitId,
        private readonly PenaltyType $type,
        private readonly DateTimeImmutable $startsAt,
        private readonly ?DateTimeImmutable $endsAt,
        private PenaltyStatus $status,
        private ?DateTimeImmutable $revokedAt,
        private ?Uuid $revokedBy,
        private ?string $revokedReason,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $violationId,
        Uuid $unitId,
        PenaltyType $type,
        DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
    ): self {
        $penalty = new self(
            id: $id,
            violationId: $violationId,
            unitId: $unitId,
            type: $type,
            startsAt: $startsAt,
            endsAt: $endsAt,
            status: PenaltyStatus::Active,
            revokedAt: null,
            revokedBy: null,
            revokedReason: null,
            createdAt: new DateTimeImmutable,
        );

        $penalty->domainEvents[] = new PenaltyApplied(
            penaltyId: $id->value(),
            violationId: $violationId->value(),
            unitId: $unitId->value(),
            type: $type->value,
            startsAt: $startsAt->format('c'),
            endsAt: $endsAt?->format('c'),
        );

        return $penalty;
    }

    public function revoke(Uuid $revokedBy, string $reason): void
    {
        if ($this->status !== PenaltyStatus::Active) {
            throw DomainException::businessRule(
                'PENALTY_NOT_ACTIVE',
                "Cannot revoke penalty with status: {$this->status->value}",
                [
                    'penalty_id' => $this->id->value(),
                    'status' => $this->status->value,
                ],
            );
        }

        $this->status = PenaltyStatus::Revoked;
        $this->revokedBy = $revokedBy;
        $this->revokedAt = new DateTimeImmutable;
        $this->revokedReason = $reason;

        $this->domainEvents[] = new PenaltyRevoked(
            penaltyId: $this->id->value(),
            unitId: $this->unitId->value(),
            revokedBy: $revokedBy->value(),
            reason: $reason,
        );
    }

    public function expire(): void
    {
        if ($this->status !== PenaltyStatus::Active) {
            throw DomainException::businessRule(
                'PENALTY_NOT_ACTIVE',
                "Cannot expire penalty with status: {$this->status->value}",
                [
                    'penalty_id' => $this->id->value(),
                    'status' => $this->status->value,
                ],
            );
        }

        $this->status = PenaltyStatus::Expired;

        $this->domainEvents[] = new PenaltyExpired(
            penaltyId: $this->id->value(),
            unitId: $this->unitId->value(),
        );
    }

    public function isActive(): bool
    {
        if ($this->status !== PenaltyStatus::Active) {
            return false;
        }

        if ($this->endsAt !== null && $this->endsAt <= new DateTimeImmutable) {
            return false;
        }

        return true;
    }

    public function isBlocking(): bool
    {
        return $this->isActive() && $this->type->isBlocking();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function violationId(): Uuid
    {
        return $this->violationId;
    }

    public function unitId(): Uuid
    {
        return $this->unitId;
    }

    public function type(): PenaltyType
    {
        return $this->type;
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function endsAt(): ?DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function status(): PenaltyStatus
    {
        return $this->status;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revokedBy(): ?Uuid
    {
        return $this->revokedBy;
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
}
