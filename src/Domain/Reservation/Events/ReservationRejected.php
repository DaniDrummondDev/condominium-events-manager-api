<?php

declare(strict_types=1);

namespace Domain\Reservation\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ReservationRejected implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $reservationId,
        public string $spaceId,
        public string $residentId,
        public string $rejectedBy,
        public string $rejectionReason,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'reservation.rejected';
    }

    public function aggregateId(): Uuid
    {
        return Uuid::fromString($this->reservationId);
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'reservation_id' => $this->reservationId,
            'space_id' => $this->spaceId,
            'resident_id' => $this->residentId,
            'rejected_by' => $this->rejectedBy,
            'rejection_reason' => $this->rejectionReason,
        ];
    }
}
