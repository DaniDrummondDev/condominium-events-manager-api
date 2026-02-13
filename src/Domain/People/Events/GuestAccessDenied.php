<?php

declare(strict_types=1);

namespace Domain\People\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class GuestAccessDenied implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $guestId,
        public string $reservationId,
        public string $deniedBy,
        public string $reason,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'guest.access_denied';
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
            'denied_by' => $this->deniedBy,
            'reason' => $this->reason,
        ];
    }
}
