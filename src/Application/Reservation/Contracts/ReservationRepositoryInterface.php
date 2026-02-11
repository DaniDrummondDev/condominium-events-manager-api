<?php

declare(strict_types=1);

namespace Application\Reservation\Contracts;

use Domain\Reservation\Entities\Reservation;
use Domain\Shared\ValueObjects\DateRange;
use Domain\Shared\ValueObjects\Uuid;

interface ReservationRepositoryInterface
{
    public function findById(Uuid $id): ?Reservation;

    /**
     * @return array<Reservation>
     */
    public function findBySpace(Uuid $spaceId): array;

    /**
     * @return array<Reservation>
     */
    public function findByUnit(Uuid $unitId): array;

    /**
     * @return array<Reservation>
     */
    public function findByResident(Uuid $residentId): array;

    /**
     * Find active reservations that conflict with the given period for the same space.
     * Implementation MUST use SELECT FOR UPDATE (pessimistic lock) on PostgreSQL.
     *
     * @return array<Reservation>
     */
    public function findConflicting(Uuid $spaceId, DateRange $period, ?Uuid $excludeReservationId = null): array;

    public function countMonthlyBySpaceAndUnit(Uuid $spaceId, Uuid $unitId, int $year, int $month): int;

    public function save(Reservation $reservation): void;
}
