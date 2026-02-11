<?php

declare(strict_types=1);

use Application\Governance\Contracts\PenaltyRepositoryInterface;
use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Reservation\DTOs\CreateReservationDTO;
use Application\Reservation\DTOs\ReservationDTO;
use Application\Reservation\UseCases\CreateReservation;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Space\Contracts\SpaceAvailabilityRepositoryInterface;
use Application\Space\Contracts\SpaceBlockRepositoryInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\Contracts\SpaceRuleRepositoryInterface;
use Application\Unit\Contracts\ResidentRepositoryInterface;
use Application\Unit\Contracts\UnitRepositoryInterface;
use Domain\Reservation\Events\ReservationConfirmed;
use Domain\Reservation\Events\ReservationRequested;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Entities\SpaceAvailability;
use Domain\Space\Entities\SpaceBlock;
use Domain\Space\Entities\SpaceRule;
use Domain\Space\Enums\SpaceType;
use Domain\Unit\Entities\Resident;
use Domain\Unit\Entities\Unit;

afterEach(fn () => Mockery::close());

function createMockSpace(
    ?Uuid $id = null,
    bool $active = true,
    bool $requiresApproval = false,
    int $capacity = 50,
    ?int $maxDurationHours = 8,
    int $maxAdvanceDays = 30,
    int $minAdvanceHours = 24,
    int $cancellationDeadlineHours = 24,
): Space {
    return Space::create(
        $id ?? Uuid::generate(),
        'Salão de Festas',
        'Salão principal',
        SpaceType::PartyHall,
        $capacity,
        $requiresApproval,
        $maxDurationHours,
        $maxAdvanceDays,
        $minAdvanceHours,
        $cancellationDeadlineHours,
    );
}

function createMockUnit(?Uuid $id = null): Unit
{
    $unit = Mockery::mock(Unit::class);
    $unit->shouldReceive('id')->andReturn($id ?? Uuid::generate());
    $unit->shouldReceive('isActive')->andReturn(true);

    return $unit;
}

function createInactiveUnit(): Unit
{
    $unit = Mockery::mock(Unit::class);
    $unit->shouldReceive('isActive')->andReturn(false);

    return $unit;
}

function createMockResident(?Uuid $id = null): Resident
{
    $resident = Mockery::mock(Resident::class);
    $resident->shouldReceive('id')->andReturn($id ?? Uuid::generate());
    $resident->shouldReceive('isActive')->andReturn(true);

    return $resident;
}

function createInactiveResident(): Resident
{
    $resident = Mockery::mock(Resident::class);
    $resident->shouldReceive('isActive')->andReturn(false);

    return $resident;
}

function createAvailability(Uuid $spaceId, int $dayOfWeek, string $startTime = '08:00', string $endTime = '22:00'): SpaceAvailability
{
    return SpaceAvailability::create(
        Uuid::generate(),
        $spaceId,
        $dayOfWeek,
        $startTime,
        $endTime,
    );
}

function createCreateReservationDTO(
    ?string $spaceId = null,
    ?string $unitId = null,
    ?string $residentId = null,
    ?string $startDatetime = null,
    ?string $endDatetime = null,
    int $expectedGuests = 10,
): CreateReservationDTO {
    $start = $startDatetime ?? (new DateTimeImmutable('+2 days 10:00'))->format('c');
    $end = $endDatetime ?? (new DateTimeImmutable('+2 days 14:00'))->format('c');

    return new CreateReservationDTO(
        spaceId: $spaceId ?? Uuid::generate()->value(),
        unitId: $unitId ?? Uuid::generate()->value(),
        residentId: $residentId ?? Uuid::generate()->value(),
        title: 'Churrasco',
        startDatetime: $start,
        endDatetime: $end,
        expectedGuests: $expectedGuests,
        notes: null,
    );
}

function createUseCase(
    ?ReservationRepositoryInterface $reservationRepo = null,
    ?SpaceRepositoryInterface $spaceRepo = null,
    ?SpaceAvailabilityRepositoryInterface $availabilityRepo = null,
    ?SpaceBlockRepositoryInterface $blockRepo = null,
    ?SpaceRuleRepositoryInterface $ruleRepo = null,
    ?UnitRepositoryInterface $unitRepo = null,
    ?ResidentRepositoryInterface $residentRepo = null,
    ?PenaltyRepositoryInterface $penaltyRepo = null,
    ?EventDispatcherInterface $eventDispatcher = null,
): CreateReservation {
    $defaultPenaltyRepo = Mockery::mock(PenaltyRepositoryInterface::class);
    $defaultPenaltyRepo->shouldReceive('hasActiveBlock')->andReturn(false);

    return new CreateReservation(
        $reservationRepo ?? Mockery::mock(ReservationRepositoryInterface::class),
        $spaceRepo ?? Mockery::mock(SpaceRepositoryInterface::class),
        $availabilityRepo ?? Mockery::mock(SpaceAvailabilityRepositoryInterface::class),
        $blockRepo ?? Mockery::mock(SpaceBlockRepositoryInterface::class),
        $ruleRepo ?? Mockery::mock(SpaceRuleRepositoryInterface::class),
        $unitRepo ?? Mockery::mock(UnitRepositoryInterface::class),
        $residentRepo ?? Mockery::mock(ResidentRepositoryInterface::class),
        $penaltyRepo ?? $defaultPenaltyRepo,
        $eventDispatcher ?? Mockery::mock(EventDispatcherInterface::class),
    );
}

// ── Happy Path ───────────────────────────────────────────────

test('creates reservation without approval and returns ReservationDTO', function () {
    $spaceId = Uuid::generate();
    $unitId = Uuid::generate();
    $residentId = Uuid::generate();

    $space = createMockSpace(id: $spaceId, requiresApproval: false);
    $unit = createMockUnit($unitId);
    $resident = createMockResident($residentId);

    $start = new DateTimeImmutable('+2 days 10:00');
    $dayOfWeek = (int) $start->format('w');

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->once()->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->once()->andReturn($unit);

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->once()->andReturn($resident);

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createAvailability($spaceId, $dayOfWeek),
    ]);

    $ruleRepo = Mockery::mock(SpaceRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findBySpaceIdAndKey')->andReturnNull();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findConflicting')->andReturn([]);
    $reservationRepo->shouldReceive('save')->once();

    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findBySpaceId')->andReturn([]);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof ReservationConfirmed);

    $useCase = createUseCase(
        reservationRepo: $reservationRepo,
        spaceRepo: $spaceRepo,
        availabilityRepo: $availabilityRepo,
        blockRepo: $blockRepo,
        ruleRepo: $ruleRepo,
        unitRepo: $unitRepo,
        residentRepo: $residentRepo,
        eventDispatcher: $eventDispatcher,
    );

    $dto = createCreateReservationDTO(
        spaceId: $spaceId->value(),
        unitId: $unitId->value(),
        residentId: $residentId->value(),
        startDatetime: $start->format('c'),
        endDatetime: $start->modify('+4 hours')->format('c'),
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(ReservationDTO::class)
        ->and($result->status)->toBe('confirmed')
        ->and($result->spaceId)->toBe($spaceId->value());
});

test('creates reservation with approval and emits ReservationRequested', function () {
    $spaceId = Uuid::generate();
    $space = createMockSpace(id: $spaceId, requiresApproval: true);

    $start = new DateTimeImmutable('+2 days 10:00');
    $dayOfWeek = (int) $start->format('w');

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn(createMockUnit());

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn(createMockResident());

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createAvailability($spaceId, $dayOfWeek),
    ]);

    $ruleRepo = Mockery::mock(SpaceRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findBySpaceIdAndKey')->andReturnNull();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findConflicting')->andReturn([]);
    $reservationRepo->shouldReceive('save')->once();

    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findBySpaceId')->andReturn([]);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof ReservationRequested);

    $useCase = createUseCase(
        reservationRepo: $reservationRepo,
        spaceRepo: $spaceRepo,
        availabilityRepo: $availabilityRepo,
        blockRepo: $blockRepo,
        ruleRepo: $ruleRepo,
        unitRepo: $unitRepo,
        residentRepo: $residentRepo,
        eventDispatcher: $eventDispatcher,
    );

    $dto = createCreateReservationDTO(
        spaceId: $spaceId->value(),
        startDatetime: $start->format('c'),
        endDatetime: $start->modify('+4 hours')->format('c'),
    );

    $result = $useCase->execute($dto);

    expect($result->status)->toBe('pending_approval');
});

// ── Validation 1: Space not found ────────────────────────────

test('throws SPACE_NOT_FOUND when space does not exist', function () {
    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturnNull();

    $useCase = createUseCase(spaceRepo: $spaceRepo);

    $useCase->execute(createCreateReservationDTO());
})->throws(DomainException::class, 'Space not found');

// ── Validation 2: Space inactive ─────────────────────────────

test('throws SPACE_INACTIVE when space is not active', function () {
    $space = createMockSpace();
    $space->deactivate(); // changes to inactive

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $useCase = createUseCase(spaceRepo: $spaceRepo);

    $useCase->execute(createCreateReservationDTO());
})->throws(DomainException::class, 'Space is not active');

// ── Validation 3: Unit not found ─────────────────────────────

test('throws UNIT_NOT_FOUND when unit does not exist', function () {
    $space = createMockSpace();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturnNull();

    $useCase = createUseCase(spaceRepo: $spaceRepo, unitRepo: $unitRepo);

    $useCase->execute(createCreateReservationDTO());
})->throws(DomainException::class, 'Unit not found');

// ── Validation 4: Unit inactive ──────────────────────────────

test('throws UNIT_INACTIVE when unit is not active', function () {
    $space = createMockSpace();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn(createInactiveUnit());

    $useCase = createUseCase(spaceRepo: $spaceRepo, unitRepo: $unitRepo);

    $useCase->execute(createCreateReservationDTO());
})->throws(DomainException::class, 'Unit is not active');

// ── Validation 5: Resident not found ─────────────────────────

test('throws RESIDENT_NOT_FOUND when resident does not exist', function () {
    $space = createMockSpace();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn(createMockUnit());

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturnNull();

    $useCase = createUseCase(spaceRepo: $spaceRepo, unitRepo: $unitRepo, residentRepo: $residentRepo);

    $useCase->execute(createCreateReservationDTO());
})->throws(DomainException::class, 'Resident not found');

// ── Validation 6: Resident inactive ──────────────────────────

test('throws RESIDENT_INACTIVE when resident is not active', function () {
    $space = createMockSpace();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn(createMockUnit());

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn(createInactiveResident());

    $useCase = createUseCase(spaceRepo: $spaceRepo, unitRepo: $unitRepo, residentRepo: $residentRepo);

    $useCase->execute(createCreateReservationDTO());
})->throws(DomainException::class, 'Resident is not active');

// ── Validation 7: Outside availability window ────────────────

test('throws OUTSIDE_AVAILABILITY_WINDOW when time is outside available hours', function () {
    $spaceId = Uuid::generate();
    $space = createMockSpace(id: $spaceId);

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn(createMockUnit());

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn(createMockResident());

    // Availability is on a different day of week
    $start = new DateTimeImmutable('+2 days 10:00');
    $differentDay = ((int) $start->format('w') + 1) % 7;

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createAvailability($spaceId, $differentDay, '08:00', '22:00'),
    ]);

    $useCase = createUseCase(
        spaceRepo: $spaceRepo,
        unitRepo: $unitRepo,
        residentRepo: $residentRepo,
        availabilityRepo: $availabilityRepo,
    );

    $dto = createCreateReservationDTO(
        spaceId: $spaceId->value(),
        startDatetime: $start->format('c'),
        endDatetime: $start->modify('+4 hours')->format('c'),
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'outside available hours');

// ── Validation 8: Min advance not met ────────────────────────

test('throws MIN_ADVANCE_NOT_MET when booking too soon', function () {
    $spaceId = Uuid::generate();
    $space = createMockSpace(id: $spaceId, minAdvanceHours: 48);

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn(createMockUnit());

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn(createMockResident());

    // Start in 12 hours (< 48h minimum)
    $start = new DateTimeImmutable('+12 hours');
    $dayOfWeek = (int) $start->format('w');

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createAvailability($spaceId, $dayOfWeek, '00:00', '23:59'),
    ]);

    $useCase = createUseCase(
        spaceRepo: $spaceRepo,
        unitRepo: $unitRepo,
        residentRepo: $residentRepo,
        availabilityRepo: $availabilityRepo,
    );

    $dto = createCreateReservationDTO(
        spaceId: $spaceId->value(),
        startDatetime: $start->format('c'),
        endDatetime: $start->modify('+2 hours')->format('c'),
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'at least');

// ── Validation 9: Max advance exceeded ───────────────────────

test('throws MAX_ADVANCE_EXCEEDED when booking too far ahead', function () {
    $spaceId = Uuid::generate();
    $space = createMockSpace(id: $spaceId, maxAdvanceDays: 7);

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn(createMockUnit());

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn(createMockResident());

    // Start in 30 days (> 7 max)
    $start = new DateTimeImmutable('+30 days 10:00');
    $dayOfWeek = (int) $start->format('w');

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createAvailability($spaceId, $dayOfWeek, '08:00', '22:00'),
    ]);

    $useCase = createUseCase(
        spaceRepo: $spaceRepo,
        unitRepo: $unitRepo,
        residentRepo: $residentRepo,
        availabilityRepo: $availabilityRepo,
    );

    $dto = createCreateReservationDTO(
        spaceId: $spaceId->value(),
        startDatetime: $start->format('c'),
        endDatetime: $start->modify('+4 hours')->format('c'),
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'more than');

// ── Validation 10: Max duration exceeded ─────────────────────

test('throws MAX_DURATION_EXCEEDED when reservation too long', function () {
    $spaceId = Uuid::generate();
    $space = createMockSpace(id: $spaceId, maxDurationHours: 2);

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn(createMockUnit());

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn(createMockResident());

    $start = new DateTimeImmutable('+2 days 10:00');
    $dayOfWeek = (int) $start->format('w');

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createAvailability($spaceId, $dayOfWeek, '08:00', '22:00'),
    ]);

    $useCase = createUseCase(
        spaceRepo: $spaceRepo,
        unitRepo: $unitRepo,
        residentRepo: $residentRepo,
        availabilityRepo: $availabilityRepo,
    );

    // Request 5 hours (> 2h max)
    $dto = createCreateReservationDTO(
        spaceId: $spaceId->value(),
        startDatetime: $start->format('c'),
        endDatetime: $start->modify('+5 hours')->format('c'),
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'duration exceeds');

// ── Validation 11: Capacity exceeded ─────────────────────────

test('throws CAPACITY_EXCEEDED when guests exceed capacity', function () {
    $spaceId = Uuid::generate();
    $space = createMockSpace(id: $spaceId, capacity: 10);

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn(createMockUnit());

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn(createMockResident());

    $start = new DateTimeImmutable('+2 days 10:00');
    $dayOfWeek = (int) $start->format('w');

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createAvailability($spaceId, $dayOfWeek, '08:00', '22:00'),
    ]);

    $useCase = createUseCase(
        spaceRepo: $spaceRepo,
        unitRepo: $unitRepo,
        residentRepo: $residentRepo,
        availabilityRepo: $availabilityRepo,
    );

    $dto = createCreateReservationDTO(
        spaceId: $spaceId->value(),
        expectedGuests: 50, // > 10 capacity
        startDatetime: $start->format('c'),
        endDatetime: $start->modify('+4 hours')->format('c'),
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'exceeds space capacity');

// ── Validation 12: Monthly limit exceeded ────────────────────

test('throws MONTHLY_LIMIT_EXCEEDED when unit exceeds monthly limit', function () {
    $spaceId = Uuid::generate();
    $space = createMockSpace(id: $spaceId);

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn(createMockUnit());

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn(createMockResident());

    $start = new DateTimeImmutable('+2 days 10:00');
    $dayOfWeek = (int) $start->format('w');

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createAvailability($spaceId, $dayOfWeek, '08:00', '22:00'),
    ]);

    $rule = Mockery::mock(SpaceRule::class);
    $rule->shouldReceive('ruleValue')->andReturn('2');

    $ruleRepo = Mockery::mock(SpaceRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findBySpaceIdAndKey')->andReturn($rule);

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('countMonthlyBySpaceAndUnit')->andReturn(2); // already at limit

    $useCase = createUseCase(
        reservationRepo: $reservationRepo,
        spaceRepo: $spaceRepo,
        availabilityRepo: $availabilityRepo,
        ruleRepo: $ruleRepo,
        unitRepo: $unitRepo,
        residentRepo: $residentRepo,
    );

    $dto = createCreateReservationDTO(
        spaceId: $spaceId->value(),
        startDatetime: $start->format('c'),
        endDatetime: $start->modify('+4 hours')->format('c'),
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'Monthly reservation limit exceeded');

// ── Validation 13: Reservation conflict ──────────────────────

test('throws RESERVATION_CONFLICT when time slot conflicts', function () {
    $spaceId = Uuid::generate();
    $space = createMockSpace(id: $spaceId);

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn(createMockUnit());

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn(createMockResident());

    $start = new DateTimeImmutable('+2 days 10:00');
    $dayOfWeek = (int) $start->format('w');

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createAvailability($spaceId, $dayOfWeek, '08:00', '22:00'),
    ]);

    $ruleRepo = Mockery::mock(SpaceRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findBySpaceIdAndKey')->andReturnNull();

    // Existing conflicting reservation
    $existingReservation = Mockery::mock(\Domain\Reservation\Entities\Reservation::class);

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findConflicting')->andReturn([$existingReservation]);

    $useCase = createUseCase(
        reservationRepo: $reservationRepo,
        spaceRepo: $spaceRepo,
        availabilityRepo: $availabilityRepo,
        ruleRepo: $ruleRepo,
        unitRepo: $unitRepo,
        residentRepo: $residentRepo,
    );

    $dto = createCreateReservationDTO(
        spaceId: $spaceId->value(),
        startDatetime: $start->format('c'),
        endDatetime: $start->modify('+4 hours')->format('c'),
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'conflicts with an existing reservation');

// ── Validation 14: Space blocked ─────────────────────────────

test('throws SPACE_BLOCKED when space has block during period', function () {
    $spaceId = Uuid::generate();
    $space = createMockSpace(id: $spaceId);

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn(createMockUnit());

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn(createMockResident());

    $start = new DateTimeImmutable('+2 days 10:00');
    $dayOfWeek = (int) $start->format('w');

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createAvailability($spaceId, $dayOfWeek, '08:00', '22:00'),
    ]);

    $ruleRepo = Mockery::mock(SpaceRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findBySpaceIdAndKey')->andReturnNull();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findConflicting')->andReturn([]);

    // Block overlapping with the reservation period
    $block = SpaceBlock::create(
        Uuid::generate(),
        $spaceId,
        'maintenance',
        new DateTimeImmutable('+2 days 08:00'),
        new DateTimeImmutable('+2 days 18:00'),
        Uuid::generate(),
        'Manutenção programada',
    );

    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findBySpaceId')->andReturn([$block]);

    $useCase = createUseCase(
        reservationRepo: $reservationRepo,
        spaceRepo: $spaceRepo,
        availabilityRepo: $availabilityRepo,
        blockRepo: $blockRepo,
        ruleRepo: $ruleRepo,
        unitRepo: $unitRepo,
        residentRepo: $residentRepo,
    );

    $dto = createCreateReservationDTO(
        spaceId: $spaceId->value(),
        startDatetime: $start->format('c'),
        endDatetime: $start->modify('+4 hours')->format('c'),
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'Space is blocked');

// ── No availability configured (skip check) ─────────────────

test('skips availability check when no availability configured for space', function () {
    $spaceId = Uuid::generate();
    $space = createMockSpace(id: $spaceId, requiresApproval: false);

    $start = new DateTimeImmutable('+2 days 10:00');

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn(createMockUnit());

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn(createMockResident());

    // No availabilities configured at all
    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([]);

    $ruleRepo = Mockery::mock(SpaceRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findBySpaceIdAndKey')->andReturnNull();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findConflicting')->andReturn([]);
    $reservationRepo->shouldReceive('save')->once();

    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findBySpaceId')->andReturn([]);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = createUseCase(
        reservationRepo: $reservationRepo,
        spaceRepo: $spaceRepo,
        availabilityRepo: $availabilityRepo,
        blockRepo: $blockRepo,
        ruleRepo: $ruleRepo,
        unitRepo: $unitRepo,
        residentRepo: $residentRepo,
        eventDispatcher: $eventDispatcher,
    );

    $dto = createCreateReservationDTO(
        spaceId: $spaceId->value(),
        startDatetime: $start->format('c'),
        endDatetime: $start->modify('+4 hours')->format('c'),
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(ReservationDTO::class);
});

// ── No max duration (null) ───────────────────────────────────

test('skips duration check when maxDurationHours is null', function () {
    $spaceId = Uuid::generate();
    $space = createMockSpace(id: $spaceId, maxDurationHours: null, requiresApproval: false);

    $start = new DateTimeImmutable('+2 days 10:00');
    $dayOfWeek = (int) $start->format('w');

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('findById')->andReturn(createMockUnit());

    $residentRepo = Mockery::mock(ResidentRepositoryInterface::class);
    $residentRepo->shouldReceive('findById')->andReturn(createMockResident());

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createAvailability($spaceId, $dayOfWeek, '00:00', '23:59'),
    ]);

    $ruleRepo = Mockery::mock(SpaceRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findBySpaceIdAndKey')->andReturnNull();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findConflicting')->andReturn([]);
    $reservationRepo->shouldReceive('save')->once();

    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findBySpaceId')->andReturn([]);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = createUseCase(
        reservationRepo: $reservationRepo,
        spaceRepo: $spaceRepo,
        availabilityRepo: $availabilityRepo,
        blockRepo: $blockRepo,
        ruleRepo: $ruleRepo,
        unitRepo: $unitRepo,
        residentRepo: $residentRepo,
        eventDispatcher: $eventDispatcher,
    );

    // Request 12 hours — should pass because maxDurationHours is null
    $dto = createCreateReservationDTO(
        spaceId: $spaceId->value(),
        startDatetime: $start->format('c'),
        endDatetime: $start->modify('+12 hours')->format('c'),
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(ReservationDTO::class);
});
