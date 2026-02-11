<?php

declare(strict_types=1);

use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Reservation\DTOs\ReservationDTO;
use Application\Reservation\UseCases\CheckInReservation;
use Domain\Reservation\Entities\Reservation;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createConfirmedForCheckIn(): Reservation
{
    $reservation = Reservation::create(
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        'Festa',
        new DateTimeImmutable('+1 day 10:00'),
        new DateTimeImmutable('+1 day 14:00'),
        20,
        null,
        false, // auto-confirmed
    );
    $reservation->pullDomainEvents();

    return $reservation;
}

test('checks in confirmed reservation and returns ReservationDTO', function () {
    $reservation = createConfirmedForCheckIn();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->once()->andReturn($reservation);
    $reservationRepo->shouldReceive('save')->once();

    $useCase = new CheckInReservation($reservationRepo);
    $result = $useCase->execute($reservation->id()->value());

    expect($result)->toBeInstanceOf(ReservationDTO::class)
        ->and($result->status)->toBe('in_progress')
        ->and($result->checkedInAt)->not->toBeNull();
});

test('throws RESERVATION_NOT_FOUND when reservation does not exist', function () {
    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturnNull();

    $useCase = new CheckInReservation($reservationRepo);

    $useCase->execute(Uuid::generate()->value());
})->throws(DomainException::class, 'Reservation not found');

test('throws INVALID_STATUS_TRANSITION when checking in pending reservation', function () {
    $reservation = Reservation::create(
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        'Festa',
        new DateTimeImmutable('+1 day 10:00'),
        new DateTimeImmutable('+1 day 14:00'),
        20,
        null,
        true, // pending_approval
    );
    $reservation->pullDomainEvents();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturn($reservation);

    $useCase = new CheckInReservation($reservationRepo);

    $useCase->execute($reservation->id()->value());
})->throws(DomainException::class, 'Cannot transition');

test('throws INVALID_STATUS_TRANSITION when checking in completed reservation', function () {
    $reservation = createConfirmedForCheckIn();
    $reservation->checkIn();
    $reservation->complete();
    $reservation->pullDomainEvents();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturn($reservation);

    $useCase = new CheckInReservation($reservationRepo);

    $useCase->execute($reservation->id()->value());
})->throws(DomainException::class, 'Cannot transition');
