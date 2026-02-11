<?php

declare(strict_types=1);

use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Reservation\DTOs\ReservationDTO;
use Application\Reservation\UseCases\CompleteReservation;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Reservation\Entities\Reservation;
use Domain\Reservation\Events\ReservationCompleted;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createInProgressForComplete(): Reservation
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
        false,
    );
    $reservation->checkIn();
    $reservation->pullDomainEvents();

    return $reservation;
}

test('completes in-progress reservation and returns ReservationDTO', function () {
    $reservation = createInProgressForComplete();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->once()->andReturn($reservation);
    $reservationRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof ReservationCompleted);

    $useCase = new CompleteReservation($reservationRepo, $eventDispatcher);
    $result = $useCase->execute($reservation->id()->value());

    expect($result)->toBeInstanceOf(ReservationDTO::class)
        ->and($result->status)->toBe('completed')
        ->and($result->completedAt)->not->toBeNull();
});

test('throws RESERVATION_NOT_FOUND when reservation does not exist', function () {
    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturnNull();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CompleteReservation($reservationRepo, $eventDispatcher);

    $useCase->execute(Uuid::generate()->value());
})->throws(DomainException::class, 'Reservation not found');

test('throws INVALID_STATUS_TRANSITION when completing confirmed reservation', function () {
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
        false,
    );
    $reservation->pullDomainEvents();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturn($reservation);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CompleteReservation($reservationRepo, $eventDispatcher);

    $useCase->execute($reservation->id()->value());
})->throws(DomainException::class, 'Cannot transition');
