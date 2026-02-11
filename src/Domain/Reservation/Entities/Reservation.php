<?php

declare(strict_types=1);

namespace Domain\Reservation\Entities;

use DateTimeImmutable;
use Domain\Reservation\Enums\ReservationStatus;
use Domain\Reservation\Events\ReservationCanceled;
use Domain\Reservation\Events\ReservationCompleted;
use Domain\Reservation\Events\ReservationConfirmed;
use Domain\Reservation\Events\ReservationNoShow;
use Domain\Reservation\Events\ReservationRejected;
use Domain\Reservation\Events\ReservationRequested;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\DateRange;
use Domain\Shared\ValueObjects\Uuid;

class Reservation
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $spaceId,
        private readonly Uuid $unitId,
        private readonly Uuid $residentId,
        private ReservationStatus $status,
        private readonly ?string $title,
        private readonly DateTimeImmutable $startDatetime,
        private readonly DateTimeImmutable $endDatetime,
        private readonly int $expectedGuests,
        private readonly ?string $notes,
        private ?Uuid $approvedBy,
        private ?DateTimeImmutable $approvedAt,
        private ?Uuid $rejectedBy,
        private ?DateTimeImmutable $rejectedAt,
        private ?string $rejectionReason,
        private ?Uuid $canceledBy,
        private ?DateTimeImmutable $canceledAt,
        private ?string $cancellationReason,
        private ?DateTimeImmutable $completedAt,
        private ?DateTimeImmutable $noShowAt,
        private ?Uuid $noShowBy,
        private ?DateTimeImmutable $checkedInAt,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $spaceId,
        Uuid $unitId,
        Uuid $residentId,
        ?string $title,
        DateTimeImmutable $startDatetime,
        DateTimeImmutable $endDatetime,
        int $expectedGuests,
        ?string $notes,
        bool $requiresApproval,
    ): self {
        $status = $requiresApproval
            ? ReservationStatus::PendingApproval
            : ReservationStatus::Confirmed;

        $reservation = new self(
            id: $id,
            spaceId: $spaceId,
            unitId: $unitId,
            residentId: $residentId,
            status: $status,
            title: $title,
            startDatetime: $startDatetime,
            endDatetime: $endDatetime,
            expectedGuests: $expectedGuests,
            notes: $notes,
            approvedBy: null,
            approvedAt: null,
            rejectedBy: null,
            rejectedAt: null,
            rejectionReason: null,
            canceledBy: null,
            canceledAt: null,
            cancellationReason: null,
            completedAt: null,
            noShowAt: null,
            noShowBy: null,
            checkedInAt: null,
            createdAt: new DateTimeImmutable,
        );

        if ($requiresApproval) {
            $reservation->domainEvents[] = new ReservationRequested(
                $id->value(),
                $spaceId->value(),
                $unitId->value(),
                $residentId->value(),
                $startDatetime->format('c'),
                $endDatetime->format('c'),
            );
        } else {
            $reservation->domainEvents[] = new ReservationConfirmed(
                $id->value(),
                $spaceId->value(),
                $unitId->value(),
                $residentId->value(),
            );
        }

        return $reservation;
    }

    // ── State Transitions ───────────────────────────────────────

    public function approve(Uuid $approvedBy): void
    {
        $this->assertTransition(ReservationStatus::Confirmed);

        $this->status = ReservationStatus::Confirmed;
        $this->approvedBy = $approvedBy;
        $this->approvedAt = new DateTimeImmutable;

        $this->domainEvents[] = new ReservationConfirmed(
            $this->id->value(),
            $this->spaceId->value(),
            $this->unitId->value(),
            $this->residentId->value(),
            $approvedBy->value(),
        );
    }

    public function reject(Uuid $rejectedBy, string $reason): void
    {
        $this->assertTransition(ReservationStatus::Rejected);

        $this->status = ReservationStatus::Rejected;
        $this->rejectedBy = $rejectedBy;
        $this->rejectedAt = new DateTimeImmutable;
        $this->rejectionReason = $reason;

        $this->domainEvents[] = new ReservationRejected(
            $this->id->value(),
            $this->spaceId->value(),
            $this->residentId->value(),
            $rejectedBy->value(),
            $reason,
        );
    }

    public function cancel(Uuid $canceledBy, string $reason, bool $isLateCancellation = false): void
    {
        $this->assertTransition(ReservationStatus::Canceled);

        $this->status = ReservationStatus::Canceled;
        $this->canceledBy = $canceledBy;
        $this->canceledAt = new DateTimeImmutable;
        $this->cancellationReason = $reason;

        $this->domainEvents[] = new ReservationCanceled(
            $this->id->value(),
            $this->spaceId->value(),
            $this->residentId->value(),
            $canceledBy->value(),
            $reason,
            $isLateCancellation,
        );
    }

    public function checkIn(): void
    {
        $this->assertTransition(ReservationStatus::InProgress);

        $this->status = ReservationStatus::InProgress;
        $this->checkedInAt = new DateTimeImmutable;
    }

    public function complete(): void
    {
        $this->assertTransition(ReservationStatus::Completed);

        $this->status = ReservationStatus::Completed;
        $this->completedAt = new DateTimeImmutable;

        $this->domainEvents[] = new ReservationCompleted(
            $this->id->value(),
            $this->spaceId->value(),
            $this->unitId->value(),
            $this->residentId->value(),
        );
    }

    public function markAsNoShow(Uuid $noShowBy): void
    {
        $this->assertTransition(ReservationStatus::NoShow);

        $this->status = ReservationStatus::NoShow;
        $this->noShowAt = new DateTimeImmutable;
        $this->noShowBy = $noShowBy;

        $this->domainEvents[] = new ReservationNoShow(
            $this->id->value(),
            $this->spaceId->value(),
            $this->unitId->value(),
            $this->residentId->value(),
            $noShowBy->value(),
        );
    }

    // ── Business Logic ──────────────────────────────────────────

    public function period(): DateRange
    {
        return new DateRange($this->startDatetime, $this->endDatetime);
    }

    public function isLateCancellation(int $cancellationDeadlineHours): bool
    {
        $now = new DateTimeImmutable;
        $hoursUntilStart = ($this->startDatetime->getTimestamp() - $now->getTimestamp()) / 3600;

        return $hoursUntilStart < $cancellationDeadlineHours;
    }

    // ── Getters ─────────────────────────────────────────────────

    public function id(): Uuid
    {
        return $this->id;
    }

    public function spaceId(): Uuid
    {
        return $this->spaceId;
    }

    public function unitId(): Uuid
    {
        return $this->unitId;
    }

    public function residentId(): Uuid
    {
        return $this->residentId;
    }

    public function status(): ReservationStatus
    {
        return $this->status;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function startDatetime(): DateTimeImmutable
    {
        return $this->startDatetime;
    }

    public function endDatetime(): DateTimeImmutable
    {
        return $this->endDatetime;
    }

    public function expectedGuests(): int
    {
        return $this->expectedGuests;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function approvedBy(): ?Uuid
    {
        return $this->approvedBy;
    }

    public function approvedAt(): ?DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function rejectedBy(): ?Uuid
    {
        return $this->rejectedBy;
    }

    public function rejectedAt(): ?DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function canceledBy(): ?Uuid
    {
        return $this->canceledBy;
    }

    public function canceledAt(): ?DateTimeImmutable
    {
        return $this->canceledAt;
    }

    public function cancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function noShowAt(): ?DateTimeImmutable
    {
        return $this->noShowAt;
    }

    public function noShowBy(): ?Uuid
    {
        return $this->noShowBy;
    }

    public function checkedInAt(): ?DateTimeImmutable
    {
        return $this->checkedInAt;
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

    private function assertTransition(ReservationStatus $newStatus): void
    {
        if (! $this->status->canTransitionTo($newStatus)) {
            throw new DomainException(
                "Cannot transition reservation from '{$this->status->value}' to '{$newStatus->value}'",
                'INVALID_STATUS_TRANSITION',
                [
                    'reservation_id' => $this->id->value(),
                    'current_status' => $this->status->value,
                    'target_status' => $newStatus->value,
                ],
            );
        }
    }
}
