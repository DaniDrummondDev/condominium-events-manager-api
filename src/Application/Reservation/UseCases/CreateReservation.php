<?php

declare(strict_types=1);

namespace Application\Reservation\UseCases;

use Application\Governance\Contracts\PenaltyRepositoryInterface;
use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Reservation\DTOs\CreateReservationDTO;
use Application\Reservation\DTOs\ReservationDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Space\Contracts\SpaceAvailabilityRepositoryInterface;
use Application\Space\Contracts\SpaceBlockRepositoryInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\Contracts\SpaceRuleRepositoryInterface;
use Application\Unit\Contracts\ResidentRepositoryInterface;
use Application\Unit\Contracts\UnitRepositoryInterface;
use DateTimeImmutable;
use Domain\Reservation\Entities\Reservation;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\DateRange;
use Domain\Shared\ValueObjects\Uuid;

final readonly class CreateReservation
{
    public function __construct(
        private ReservationRepositoryInterface $reservationRepository,
        private SpaceRepositoryInterface $spaceRepository,
        private SpaceAvailabilityRepositoryInterface $availabilityRepository,
        private SpaceBlockRepositoryInterface $blockRepository,
        private SpaceRuleRepositoryInterface $ruleRepository,
        private UnitRepositoryInterface $unitRepository,
        private ResidentRepositoryInterface $residentRepository,
        private PenaltyRepositoryInterface $penaltyRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(CreateReservationDTO $dto): ReservationDTO
    {
        $spaceId = Uuid::fromString($dto->spaceId);
        $unitId = Uuid::fromString($dto->unitId);
        $residentId = Uuid::fromString($dto->residentId);
        $startDatetime = new DateTimeImmutable($dto->startDatetime);
        $endDatetime = new DateTimeImmutable($dto->endDatetime);
        $period = new DateRange($startDatetime, $endDatetime);

        // 1. Space exists and is active
        $space = $this->spaceRepository->findById($spaceId);

        if ($space === null) {
            throw new DomainException('Space not found', 'SPACE_NOT_FOUND', ['space_id' => $dto->spaceId]);
        }

        if (! $space->canAcceptReservations()) {
            throw new DomainException('Space is not active', 'SPACE_INACTIVE', ['space_id' => $dto->spaceId]);
        }

        // 2. Unit exists and is active
        $unit = $this->unitRepository->findById($unitId);

        if ($unit === null) {
            throw new DomainException('Unit not found', 'UNIT_NOT_FOUND', ['unit_id' => $dto->unitId]);
        }

        if (! $unit->isActive()) {
            throw new DomainException('Unit is not active', 'UNIT_INACTIVE', ['unit_id' => $dto->unitId]);
        }

        // 3. Resident exists and is active
        $resident = $this->residentRepository->findById($residentId);

        if ($resident === null) {
            throw new DomainException('Resident not found', 'RESIDENT_NOT_FOUND', ['resident_id' => $dto->residentId]);
        }

        if (! $resident->isActive()) {
            throw new DomainException('Resident is not active', 'RESIDENT_INACTIVE', ['resident_id' => $dto->residentId]);
        }

        // 4. Validate time within SpaceAvailability windows
        $dayOfWeek = (int) $startDatetime->format('w');
        $availabilities = $this->availabilityRepository->findBySpaceId($spaceId);
        $startTime = $startDatetime->format('H:i');
        $endTime = $endDatetime->format('H:i');

        $withinAvailability = false;

        foreach ($availabilities as $availability) {
            if ($availability->dayOfWeek() === $dayOfWeek
                && $startTime >= $availability->startTime()
                && $endTime <= $availability->endTime()) {
                $withinAvailability = true;

                break;
            }
        }

        if (! $withinAvailability && count($availabilities) > 0) {
            throw new DomainException(
                'Reservation time is outside available hours',
                'OUTSIDE_AVAILABILITY_WINDOW',
                ['day_of_week' => $dayOfWeek, 'start_time' => $startTime, 'end_time' => $endTime],
            );
        }

        // 5. Min advance hours
        $now = new DateTimeImmutable;
        $hoursUntilStart = ($startDatetime->getTimestamp() - $now->getTimestamp()) / 3600;

        if ($hoursUntilStart < $space->minAdvanceHours()) {
            throw new DomainException(
                "Reservation must be made at least {$space->minAdvanceHours()} hours in advance",
                'MIN_ADVANCE_NOT_MET',
                ['min_advance_hours' => $space->minAdvanceHours(), 'hours_until_start' => round($hoursUntilStart, 1)],
            );
        }

        // 6. Max advance days
        $daysUntilStart = $hoursUntilStart / 24;

        if ($daysUntilStart > $space->maxAdvanceDays()) {
            throw new DomainException(
                "Reservation cannot be made more than {$space->maxAdvanceDays()} days in advance",
                'MAX_ADVANCE_EXCEEDED',
                ['max_advance_days' => $space->maxAdvanceDays(), 'days_until_start' => round($daysUntilStart, 1)],
            );
        }

        // 7. Max duration
        if ($space->maxDurationHours() !== null) {
            $durationHours = $period->durationInMinutes() / 60;

            if ($durationHours > $space->maxDurationHours()) {
                throw new DomainException(
                    "Reservation duration exceeds maximum of {$space->maxDurationHours()} hours",
                    'MAX_DURATION_EXCEEDED',
                    ['max_duration_hours' => $space->maxDurationHours(), 'requested_hours' => $durationHours],
                );
            }
        }

        // 8. Capacity
        if ($dto->expectedGuests > $space->capacity()) {
            throw new DomainException(
                "Expected guests ({$dto->expectedGuests}) exceeds space capacity ({$space->capacity()})",
                'CAPACITY_EXCEEDED',
                ['expected_guests' => $dto->expectedGuests, 'capacity' => $space->capacity()],
            );
        }

        // 9. Monthly limit per unit (from space rules)
        $monthlyLimitRule = $this->ruleRepository->findBySpaceIdAndKey($spaceId, 'max_reservations_per_unit_per_month');

        if ($monthlyLimitRule !== null) {
            $monthlyLimit = (int) $monthlyLimitRule->ruleValue();
            $currentMonthCount = $this->reservationRepository->countMonthlyBySpaceAndUnit(
                $spaceId,
                $unitId,
                (int) $startDatetime->format('Y'),
                (int) $startDatetime->format('n'),
            );

            if ($currentMonthCount >= $monthlyLimit) {
                throw new DomainException(
                    "Monthly reservation limit exceeded for this space ({$monthlyLimit})",
                    'MONTHLY_LIMIT_EXCEEDED',
                    ['limit' => $monthlyLimit, 'current_count' => $currentMonthCount],
                );
            }
        }

        // 10. Conflict check (with pessimistic lock)
        $conflicts = $this->reservationRepository->findConflicting($spaceId, $period);

        if (count($conflicts) > 0) {
            throw new DomainException(
                'Time slot conflicts with an existing reservation',
                'RESERVATION_CONFLICT',
                ['space_id' => $dto->spaceId, 'start' => $dto->startDatetime, 'end' => $dto->endDatetime],
            );
        }

        // 11. Active penalties
        $hasBlock = $this->penaltyRepository->hasActiveBlock($unitId);
        if ($hasBlock) {
            throw DomainException::businessRule('RESIDENT_HAS_ACTIVE_PENALTY', 'Unit has an active block penalty', [
                'unit_id' => $unitId->value(),
            ]);
        }

        // 12. Space blocks in period
        $blocks = $this->blockRepository->findBySpaceId($spaceId);

        foreach ($blocks as $block) {
            $blockRange = new DateRange($block->startDatetime(), $block->endDatetime());

            if ($period->overlaps($blockRange)) {
                throw new DomainException(
                    'Space is blocked during the requested period',
                    'SPACE_BLOCKED',
                    ['space_id' => $dto->spaceId, 'block_reason' => $block->reason()],
                );
            }
        }

        // 13. Create reservation
        $reservation = Reservation::create(
            Uuid::generate(),
            $spaceId,
            $unitId,
            $residentId,
            $dto->title,
            $startDatetime,
            $endDatetime,
            $dto->expectedGuests,
            $dto->notes,
            $space->requiresApproval(),
        );

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
