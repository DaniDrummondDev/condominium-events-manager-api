<?php

declare(strict_types=1);

namespace Application\People\UseCases;

use Application\People\Contracts\GuestRepositoryInterface;
use Application\People\DTOs\GuestDTO;
use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Reservation\Enums\ReservationStatus;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class CheckInGuest
{
    public function __construct(
        private GuestRepositoryInterface $guestRepository,
        private ReservationRepositoryInterface $reservationRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(string $guestId, string $checkedInBy): GuestDTO
    {
        $guest = $this->guestRepository->findById(Uuid::fromString($guestId));

        if ($guest === null) {
            throw new DomainException(
                'Guest not found',
                'GUEST_NOT_FOUND',
                ['guest_id' => $guestId],
            );
        }

        $reservation = $this->reservationRepository->findById($guest->reservationId());

        if ($reservation === null || $reservation->status() !== ReservationStatus::InProgress) {
            throw new DomainException(
                'Reservation must be in progress for guest check-in',
                'RESERVATION_NOT_IN_PROGRESS',
                [
                    'guest_id' => $guestId,
                    'reservation_id' => $guest->reservationId()->value(),
                ],
            );
        }

        $guest->checkIn(Uuid::fromString($checkedInBy));

        $this->guestRepository->save($guest);
        $this->eventDispatcher->dispatchAll($guest->pullDomainEvents());

        return RegisterGuest::toDTO($guest);
    }
}
