<?php

declare(strict_types=1);

namespace Domain\People\Entities;

use DateTimeImmutable;
use Domain\People\Enums\ServiceProviderVisitStatus;
use Domain\People\Events\ServiceProviderCheckedIn;
use Domain\People\Events\ServiceProviderCheckedOut;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

class ServiceProviderVisit
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $serviceProviderId,
        private readonly Uuid $unitId,
        private readonly ?Uuid $reservationId,
        private readonly DateTimeImmutable $scheduledDate,
        private readonly string $purpose,
        private ServiceProviderVisitStatus $status,
        private ?DateTimeImmutable $checkedInAt,
        private ?DateTimeImmutable $checkedOutAt,
        private ?Uuid $checkedInBy,
        private readonly ?string $notes,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $serviceProviderId,
        Uuid $unitId,
        ?Uuid $reservationId,
        DateTimeImmutable $scheduledDate,
        string $purpose,
        ?string $notes,
    ): self {
        return new self(
            id: $id,
            serviceProviderId: $serviceProviderId,
            unitId: $unitId,
            reservationId: $reservationId,
            scheduledDate: $scheduledDate,
            purpose: $purpose,
            status: ServiceProviderVisitStatus::Scheduled,
            checkedInAt: null,
            checkedOutAt: null,
            checkedInBy: null,
            notes: $notes,
            createdAt: new DateTimeImmutable,
        );
    }

    // ── State Transitions ───────────────────────────────────────

    public function checkIn(Uuid $checkedInBy): void
    {
        $this->assertTransition(ServiceProviderVisitStatus::CheckedIn);

        $this->status = ServiceProviderVisitStatus::CheckedIn;
        $this->checkedInAt = new DateTimeImmutable;
        $this->checkedInBy = $checkedInBy;

        $this->domainEvents[] = new ServiceProviderCheckedIn(
            $this->id->value(),
            $this->serviceProviderId->value(),
            $this->unitId->value(),
            $checkedInBy->value(),
        );
    }

    public function checkOut(Uuid $checkedOutBy): void
    {
        $this->assertTransition(ServiceProviderVisitStatus::CheckedOut);

        $this->status = ServiceProviderVisitStatus::CheckedOut;
        $this->checkedOutAt = new DateTimeImmutable;

        $this->domainEvents[] = new ServiceProviderCheckedOut(
            $this->id->value(),
            $this->serviceProviderId->value(),
            $this->unitId->value(),
            $checkedOutBy->value(),
        );
    }

    public function cancel(): void
    {
        $this->assertTransition(ServiceProviderVisitStatus::Canceled);

        $this->status = ServiceProviderVisitStatus::Canceled;
    }

    public function markAsNoShow(): void
    {
        $this->assertTransition(ServiceProviderVisitStatus::NoShow);

        $this->status = ServiceProviderVisitStatus::NoShow;
    }

    // ── Getters ─────────────────────────────────────────────────

    public function id(): Uuid
    {
        return $this->id;
    }

    public function serviceProviderId(): Uuid
    {
        return $this->serviceProviderId;
    }

    public function unitId(): Uuid
    {
        return $this->unitId;
    }

    public function reservationId(): ?Uuid
    {
        return $this->reservationId;
    }

    public function scheduledDate(): DateTimeImmutable
    {
        return $this->scheduledDate;
    }

    public function purpose(): string
    {
        return $this->purpose;
    }

    public function status(): ServiceProviderVisitStatus
    {
        return $this->status;
    }

    public function checkedInAt(): ?DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    public function checkedOutAt(): ?DateTimeImmutable
    {
        return $this->checkedOutAt;
    }

    public function checkedInBy(): ?Uuid
    {
        return $this->checkedInBy;
    }

    public function notes(): ?string
    {
        return $this->notes;
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

    // ── Private ─────────────────────────────────────────────────

    private function assertTransition(ServiceProviderVisitStatus $newStatus): void
    {
        if (! $this->status->canTransitionTo($newStatus)) {
            throw new DomainException(
                "Cannot transition visit from '{$this->status->value}' to '{$newStatus->value}'",
                'INVALID_STATUS_TRANSITION',
                [
                    'visit_id' => $this->id->value(),
                    'current_status' => $this->status->value,
                    'target_status' => $newStatus->value,
                ],
            );
        }
    }
}
