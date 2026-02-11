<?php

declare(strict_types=1);

namespace Domain\Reservation\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ReservationCompleted implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $reservationId,
        public string $spaceId,
        public string $unitId,
        public string $residentId,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'reservation.completed';
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
            'unit_id' => $this->unitId,
            'resident_id' => $this->residentId,
        ];
    }
}
