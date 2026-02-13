<?php

declare(strict_types=1);

namespace Application\People\Contracts;

use Domain\People\Entities\Guest;
use Domain\Shared\ValueObjects\Uuid;

interface GuestRepositoryInterface
{
    public function findById(Uuid $id): ?Guest;

    /**
     * @return array<Guest>
     */
    public function findByReservation(Uuid $reservationId): array;

    public function countByReservation(Uuid $reservationId): int;

    public function save(Guest $guest): void;
}
