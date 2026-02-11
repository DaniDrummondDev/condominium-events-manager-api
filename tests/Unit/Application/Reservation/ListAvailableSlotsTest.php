<?php

declare(strict_types=1);

use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Reservation\DTOs\AvailableSlotDTO;
use Application\Reservation\DTOs\ListAvailableSlotsDTO;
use Application\Reservation\UseCases\ListAvailableSlots;
use Application\Space\Contracts\SpaceAvailabilityRepositoryInterface;
use Application\Space\Contracts\SpaceBlockRepositoryInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Domain\Reservation\Entities\Reservation;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Entities\SpaceAvailability;
use Domain\Space\Entities\SpaceBlock;
use Domain\Space\Enums\SpaceType;

afterEach(fn () => Mockery::close());

function createSlotSpace(?Uuid $id = null): Space
{
    return Space::create(
        $id ?? Uuid::generate(),
        'Salão',
        null,
        SpaceType::PartyHall,
        50,
        false,
        8,
        30,
        24,
        24,
    );
}

function createSlotAvailability(Uuid $spaceId, int $dayOfWeek, string $start = '08:00', string $end = '18:00'): SpaceAvailability
{
    return SpaceAvailability::create(
        Uuid::generate(),
        $spaceId,
        $dayOfWeek,
        $start,
        $end,
    );
}

test('returns empty array when no availability for the day', function () {
    $spaceId = Uuid::generate();
    $space = createSlotSpace($spaceId);

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([]);

    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);

    $useCase = new ListAvailableSlots($spaceRepo, $availabilityRepo, $blockRepo, $reservationRepo);
    $dto = new ListAvailableSlotsDTO($spaceId->value(), (new DateTimeImmutable('+2 days'))->format('Y-m-d'));

    $result = $useCase->execute($dto);

    expect($result)->toBe([]);
});

test('throws SPACE_NOT_FOUND when space does not exist', function () {
    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturnNull();

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);

    $useCase = new ListAvailableSlots($spaceRepo, $availabilityRepo, $blockRepo, $reservationRepo);
    $dto = new ListAvailableSlotsDTO(Uuid::generate()->value(), '2026-03-15');

    $useCase->execute($dto);
})->throws(DomainException::class, 'Space not found');

test('returns available slots for a day with availability', function () {
    $spaceId = Uuid::generate();
    $space = createSlotSpace($spaceId);
    $date = new DateTimeImmutable('+2 days');
    $dayOfWeek = (int) $date->format('w');

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createSlotAvailability($spaceId, $dayOfWeek, '10:00', '14:00'),
    ]);

    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findBySpaceId')->andReturn([]);

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findConflicting')->andReturn([]);

    $useCase = new ListAvailableSlots($spaceRepo, $availabilityRepo, $blockRepo, $reservationRepo);
    $dto = new ListAvailableSlotsDTO($spaceId->value(), $date->format('Y-m-d'));

    $result = $useCase->execute($dto);

    // 10:00-14:00 = 4 slots of 1h
    expect($result)->toHaveCount(4)
        ->and($result[0])->toBeInstanceOf(AvailableSlotDTO::class)
        ->and($result[0]->startTime)->toBe('10:00')
        ->and($result[0]->endTime)->toBe('11:00')
        ->and($result[0]->available)->toBeTrue()
        ->and($result[3]->startTime)->toBe('13:00')
        ->and($result[3]->endTime)->toBe('14:00');
});

test('marks slots as unavailable when reservation exists', function () {
    $spaceId = Uuid::generate();
    $space = createSlotSpace($spaceId);
    $date = new DateTimeImmutable('+2 days');
    $dayOfWeek = (int) $date->format('w');

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createSlotAvailability($spaceId, $dayOfWeek, '10:00', '14:00'),
    ]);

    // Existing reservation from 11:00-13:00
    $existingReservation = Reservation::create(
        Uuid::generate(),
        $spaceId,
        Uuid::generate(),
        Uuid::generate(),
        'Reserva existente',
        $date->setTime(11, 0),
        $date->setTime(13, 0),
        10,
        null,
        false,
    );

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findConflicting')->andReturn([$existingReservation]);

    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findBySpaceId')->andReturn([]);

    $useCase = new ListAvailableSlots($spaceRepo, $availabilityRepo, $blockRepo, $reservationRepo);
    $dto = new ListAvailableSlotsDTO($spaceId->value(), $date->format('Y-m-d'));

    $result = $useCase->execute($dto);

    // 10:00-11:00 available, 11:00-12:00 unavailable, 12:00-13:00 unavailable, 13:00-14:00 available
    expect($result)->toHaveCount(4)
        ->and($result[0]->available)->toBeTrue()  // 10:00-11:00
        ->and($result[1]->available)->toBeFalse() // 11:00-12:00
        ->and($result[2]->available)->toBeFalse() // 12:00-13:00
        ->and($result[3]->available)->toBeTrue(); // 13:00-14:00
});

test('marks slots as unavailable when space is blocked', function () {
    $spaceId = Uuid::generate();
    $space = createSlotSpace($spaceId);
    $date = new DateTimeImmutable('+2 days');
    $dayOfWeek = (int) $date->format('w');

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createSlotAvailability($spaceId, $dayOfWeek, '10:00', '14:00'),
    ]);

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findConflicting')->andReturn([]);

    // Block from 12:00-14:00
    $block = SpaceBlock::create(
        Uuid::generate(),
        $spaceId,
        'maintenance',
        $date->setTime(12, 0),
        $date->setTime(14, 0),
        Uuid::generate(),
        'Manutenção',
    );

    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findBySpaceId')->andReturn([$block]);

    $useCase = new ListAvailableSlots($spaceRepo, $availabilityRepo, $blockRepo, $reservationRepo);
    $dto = new ListAvailableSlotsDTO($spaceId->value(), $date->format('Y-m-d'));

    $result = $useCase->execute($dto);

    expect($result)->toHaveCount(4)
        ->and($result[0]->available)->toBeTrue()  // 10:00-11:00
        ->and($result[1]->available)->toBeTrue()  // 11:00-12:00
        ->and($result[2]->available)->toBeFalse() // 12:00-13:00
        ->and($result[3]->available)->toBeFalse(); // 13:00-14:00
});

test('returns all slots as available when no reservations or blocks', function () {
    $spaceId = Uuid::generate();
    $space = createSlotSpace($spaceId);
    $date = new DateTimeImmutable('+2 days');
    $dayOfWeek = (int) $date->format('w');

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceId')->andReturn([
        createSlotAvailability($spaceId, $dayOfWeek, '08:00', '12:00'),
    ]);

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findConflicting')->andReturn([]);

    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findBySpaceId')->andReturn([]);

    $useCase = new ListAvailableSlots($spaceRepo, $availabilityRepo, $blockRepo, $reservationRepo);
    $dto = new ListAvailableSlotsDTO($spaceId->value(), $date->format('Y-m-d'));

    $result = $useCase->execute($dto);

    expect($result)->toHaveCount(4);

    foreach ($result as $slot) {
        expect($slot->available)->toBeTrue();
    }
});
