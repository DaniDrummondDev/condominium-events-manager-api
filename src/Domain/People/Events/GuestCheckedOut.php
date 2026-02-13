<?php

declare(strict_types=1);

namespace Domain\People\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class GuestCheckedOut implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $guestId,
        public string $reservationId,
        public string $checkedOutBy,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'guest.checked_out';
    }

    public function aggregateId(): Uuid
    {
        return Uuid::fromString($this->guestId);
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
            'guest_id' => $this->guestId,
            'reservation_id' => $this->reservationId,
            'checked_out_by' => $this->checkedOutBy,
        ];
    }
}
