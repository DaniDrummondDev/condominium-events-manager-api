<?php

declare(strict_types=1);

namespace Application\Reservation\UseCases;

use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Reservation\DTOs\ReservationDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Reservation\Entities\Reservation;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ApproveReservation
{
    public function __construct(
        private ReservationRepositoryInterface $reservationRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(string $reservationId, string $approvedBy): ReservationDTO
    {
        $reservation = $this->reservationRepository->findById(Uuid::fromString($reservationId));

        if ($reservation === null) {
            throw new DomainException(
                'Reservation not found',
                'RESERVATION_NOT_FOUND',
                ['reservation_id' => $reservationId],
            );
        }

        // Re-validate conflicts before approving
        $conflicts = $this->reservationRepository->findConflicting(
            $reservation->spaceId(),
            $reservation->period(),
            $reservation->id(),
        );

        if (count($conflicts) > 0) {
            throw new DomainException(
                'A conflicting reservation was created since this request',
                'RESERVATION_CONFLICT',
                ['reservation_id' => $reservationId],
            );
        }

        $reservation->approve(Uuid::fromString($approvedBy));

        $this->reservationRepository->save($reservation);
        $this->eventDispatcher->dispatchAll($reservation->pullDomainEvents());

        return $this->toDTO($reservation);
    }

    private function toDTO(Reservation $reservation): ReservationDTO
    {
        return new ReservationDTO(
            id: $reservation->id()->value(),
            spaceId: $reservation->spaceId()->value(),
            unitId: $reservation->unitId()->value(),
            residentId: $reservation->residentId()->value(),
            status: $reservation->status()->value,
            title: $reservation->title(),
            startDatetime: $reservation->startDatetime()->format('c'),
            endDatetime: $reservation->endDatetime()->format('c'),
            expectedGuests: $reservation->expectedGuests(),
            notes: $reservation->notes(),
            approvedBy: $reservation->approvedBy()?->value(),
            approvedAt: $reservation->approvedAt()?->format('c'),
            rejectedBy: $reservation->rejectedBy()?->value(),
            rejectedAt: $reservation->rejectedAt()?->format('c'),
            rejectionReason: $reservation->rejectionReason(),
            canceledBy: $reservation->canceledBy()?->value(),
            canceledAt: $reservation->canceledAt()?->format('c'),
            cancellationReason: $reservation->cancellationReason(),
            completedAt: $reservation->completedAt()?->format('c'),
            noShowAt: $reservation->noShowAt()?->format('c'),
            noShowBy: $reservation->noShowBy()?->value(),
            checkedInAt: $reservation->checkedInAt()?->format('c'),
            createdAt: $reservation->createdAt()->format('c'),
        );
    }
}
