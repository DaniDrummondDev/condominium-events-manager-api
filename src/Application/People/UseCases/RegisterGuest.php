<?php

declare(strict_types=1);

namespace Application\People\UseCases;

use Application\People\Contracts\GuestRepositoryInterface;
use Application\People\DTOs\GuestDTO;
use Application\People\DTOs\RegisterGuestDTO;
use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Domain\People\Entities\Guest;
use Domain\Reservation\Enums\ReservationStatus;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class RegisterGuest
{
    public function __construct(
        private GuestRepositoryInterface $guestRepository,
        private ReservationRepositoryInterface $reservationRepository,
        private SpaceRepositoryInterface $spaceRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(RegisterGuestDTO $dto): GuestDTO
    {
        $reservation = $this->reservationRepository->findById(Uuid::fromString($dto->reservationId));

        if ($reservation === null) {
            throw new DomainException(
                'Reservation not found',
                'RESERVATION_NOT_FOUND',
                ['reservation_id' => $dto->reservationId],
            );
        }

        if (! in_array($reservation->status(), [ReservationStatus::Confirmed, ReservationStatus::InProgress], true)) {
            throw new DomainException(
                'Reservation must be confirmed or in progress to register guests',
                'RESERVATION_NOT_CONFIRMED',
                [
                    'reservation_id' => $dto->reservationId,
                    'current_status' => $reservation->status()->value,
                ],
            );
        }

        $space = $this->spaceRepository->findById($reservation->spaceId());

        if ($space === null) {
            throw new DomainException(
                'Space not found',
                'SPACE_NOT_FOUND',
                ['space_id' => $reservation->spaceId()->value()],
            );
        }

        $currentGuestCount = $this->guestRepository->countByReservation(Uuid::fromString($dto->reservationId));

        if ($currentGuestCount >= $space->capacity()) {
            throw new DomainException(
                'Guest limit for this space has been reached',
                'GUEST_LIMIT_EXCEEDED',
                [
                    'reservation_id' => $dto->reservationId,
                    'space_capacity' => $space->capacity(),
                    'current_guests' => $currentGuestCount,
                ],
            );
        }

        if ($currentGuestCount >= $reservation->expectedGuests()) {
            throw new DomainException(
                'Expected guest count for this reservation has been reached',
                'GUEST_LIMIT_EXCEEDED',
                [
                    'reservation_id' => $dto->reservationId,
                    'expected_guests' => $reservation->expectedGuests(),
                    'current_guests' => $currentGuestCount,
                ],
            );
        }

        $guest = Guest::create(
            id: Uuid::generate(),
            reservationId: Uuid::fromString($dto->reservationId),
            name: $dto->name,
            document: $dto->document,
            phone: $dto->phone,
            vehiclePlate: $dto->vehiclePlate,
            relationship: $dto->relationship,
            registeredBy: Uuid::fromString($dto->registeredBy),
        );

        $this->guestRepository->save($guest);
        $this->eventDispatcher->dispatchAll($guest->pullDomainEvents());

        return self::toDTO($guest);
    }

    public static function toDTO(Guest $guest): GuestDTO
    {
        return new GuestDTO(
            id: $guest->id()->value(),
            reservationId: $guest->reservationId()->value(),
            name: $guest->name(),
            document: $guest->document(),
            phone: $guest->phone(),
            vehiclePlate: $guest->vehiclePlate(),
            relationship: $guest->relationship(),
            status: $guest->status()->value,
            checkedInAt: $guest->checkedInAt()?->format('c'),
            checkedOutAt: $guest->checkedOutAt()?->format('c'),
            checkedInBy: $guest->checkedInBy()?->value(),
            deniedBy: $guest->deniedBy()?->value(),
            deniedReason: $guest->deniedReason(),
            registeredBy: $guest->registeredBy()->value(),
            createdAt: $guest->createdAt()->format('c'),
        );
    }
}
