<?php

declare(strict_types=1);

namespace Application\Reservation\DTOs;

final readonly class ReservationDTO
{
    public function __construct(
        public string $id,
        public string $spaceId,
        public string $unitId,
        public string $residentId,
        public string $status,
        public ?string $title,
        public string $startDatetime,
        public string $endDatetime,
        public int $expectedGuests,
        public ?string $notes,
        public ?string $approvedBy,
        public ?string $approvedAt,
        public ?string $rejectedBy,
        public ?string $rejectedAt,
        public ?string $rejectionReason,
        public ?string $canceledBy,
        public ?string $canceledAt,
        public ?string $cancellationReason,
        public ?string $completedAt,
        public ?string $noShowAt,
        public ?string $noShowBy,
        public ?string $checkedInAt,
        public string $createdAt,
    ) {}
}
