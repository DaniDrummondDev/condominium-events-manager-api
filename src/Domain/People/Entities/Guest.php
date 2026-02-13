<?php

declare(strict_types=1);

namespace Domain\People\Entities;

use DateTimeImmutable;
use Domain\People\Enums\GuestStatus;
use Domain\People\Events\GuestAccessDenied;
use Domain\People\Events\GuestCheckedIn;
use Domain\People\Events\GuestCheckedOut;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

class Guest
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $reservationId,
        private readonly string $name,
        private readonly ?string $document,
        private readonly ?string $phone,
        private readonly ?string $vehiclePlate,
        private readonly ?string $relationship,
        private GuestStatus $status,
        private ?DateTimeImmutable $checkedInAt,
        private ?DateTimeImmutable $checkedOutAt,
        private ?Uuid $checkedInBy,
        private ?Uuid $deniedBy,
        private ?string $deniedReason,
        private readonly Uuid $registeredBy,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $reservationId,
        string $name,
        ?string $document,
        ?string $phone,
        ?string $vehiclePlate,
        ?string $relationship,
        Uuid $registeredBy,
    ): self {
        return new self(
            id: $id,
            reservationId: $reservationId,
            name: $name,
            document: $document,
            phone: $phone,
            vehiclePlate: $vehiclePlate,
            relationship: $relationship,
            status: GuestStatus::Registered,
            checkedInAt: null,
            checkedOutAt: null,
            checkedInBy: null,
            deniedBy: null,
            deniedReason: null,
            registeredBy: $registeredBy,
            createdAt: new DateTimeImmutable,
        );
    }

    // ── State Transitions ───────────────────────────────────────

    public function checkIn(Uuid $checkedInBy): void
    {
        $this->assertTransition(GuestStatus::CheckedIn);

        $this->status = GuestStatus::CheckedIn;
        $this->checkedInAt = new DateTimeImmutable;
        $this->checkedInBy = $checkedInBy;

        $this->domainEvents[] = new GuestCheckedIn(
            $this->id->value(),
            $this->reservationId->value(),
            $checkedInBy->value(),
        );
    }

    public function checkOut(Uuid $checkedOutBy): void
    {
        $this->assertTransition(GuestStatus::CheckedOut);

        $this->status = GuestStatus::CheckedOut;
        $this->checkedOutAt = new DateTimeImmutable;

        $this->domainEvents[] = new GuestCheckedOut(
            $this->id->value(),
            $this->reservationId->value(),
            $checkedOutBy->value(),
        );
    }

    public function deny(Uuid $deniedBy, string $reason): void
    {
        $this->assertTransition(GuestStatus::Denied);

        $this->status = GuestStatus::Denied;
        $this->deniedBy = $deniedBy;
        $this->deniedReason = $reason;

        $this->domainEvents[] = new GuestAccessDenied(
            $this->id->value(),
            $this->reservationId->value(),
            $deniedBy->value(),
            $reason,
        );
    }

    public function markAsNoShow(): void
    {
        $this->assertTransition(GuestStatus::NoShow);

        $this->status = GuestStatus::NoShow;
    }

    // ── Getters ─────────────────────────────────────────────────

    public function id(): Uuid
    {
        return $this->id;
    }

    public function reservationId(): Uuid
    {
        return $this->reservationId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function document(): ?string
    {
        return $this->document;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function vehiclePlate(): ?string
    {
        return $this->vehiclePlate;
    }

    public function relationship(): ?string
    {
        return $this->relationship;
    }

    public function status(): GuestStatus
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

    public function deniedBy(): ?Uuid
    {
        return $this->deniedBy;
    }

    public function deniedReason(): ?string
    {
        return $this->deniedReason;
    }

    public function registeredBy(): Uuid
    {
        return $this->registeredBy;
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

    private function assertTransition(GuestStatus $newStatus): void
    {
        if (! $this->status->canTransitionTo($newStatus)) {
            throw new DomainException(
                "Cannot transition guest from '{$this->status->value}' to '{$newStatus->value}'",
                'INVALID_STATUS_TRANSITION',
                [
                    'guest_id' => $this->id->value(),
                    'current_status' => $this->status->value,
                    'target_status' => $newStatus->value,
                ],
            );
        }
    }
}
