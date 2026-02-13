<?php

declare(strict_types=1);

namespace Application\People\UseCases;

use Application\People\Contracts\GuestRepositoryInterface;
use Application\People\DTOs\GuestDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class CheckOutGuest
{
    public function __construct(
        private GuestRepositoryInterface $guestRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(string $guestId, string $checkedOutBy): GuestDTO
    {
        $guest = $this->guestRepository->findById(Uuid::fromString($guestId));

        if ($guest === null) {
            throw new DomainException(
                'Guest not found',
                'GUEST_NOT_FOUND',
                ['guest_id' => $guestId],
            );
        }

        $guest->checkOut(Uuid::fromString($checkedOutBy));

        $this->guestRepository->save($guest);
        $this->eventDispatcher->dispatchAll($guest->pullDomainEvents());

        return RegisterGuest::toDTO($guest);
    }
}
