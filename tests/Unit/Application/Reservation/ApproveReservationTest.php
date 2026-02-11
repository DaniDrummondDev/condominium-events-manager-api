<?php

declare(strict_types=1);

use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Reservation\DTOs\ReservationDTO;
use Application\Reservation\UseCases\ApproveReservation;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Reservation\Entities\Reservation;
use Domain\Reservation\Events\ReservationConfirmed;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createPendingReservation(): Reservation
{
    return Reservation::create(
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        'Festa',
        new DateTimeImmutable('+1 day 10:00'),
        new DateTimeImmutable('+1 day 14:00'),
        20,
        null,
        true, // requiresApproval → pending_approval
    );
}

test('approves pending reservation and returns ReservationDTO', function () {
    $reservation = createPendingReservation();
    $reservation->pullDomainEvents();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->once()->andReturn($reservation);
    $reservationRepo->shouldReceive('findConflicting')->once()->andReturn([]);
    $reservationRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof ReservationConfirmed);

    $useCase = new ApproveReservation($reservationRepo, $eventDispatcher);
    $result = $useCase->execute($reservation->id()->value(), Uuid::generate()->value());

    expect($result)->toBeInstanceOf(ReservationDTO::class)
        ->and($result->status)->toBe('confirmed')
        ->and($result->approvedBy)->not->toBeNull();
});

test('throws RESERVATION_NOT_FOUND when reservation does not exist', function () {
    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturnNull();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ApproveReservation($reservationRepo, $eventDispatcher);

    $useCase->execute(Uuid::generate()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'Reservation not found');

test('throws RESERVATION_CONFLICT when conflicting reservation exists at approval time', function () {
    $reservation = createPendingReservation();
    $reservation->pullDomainEvents();

    $conflicting = Mockery::mock(Reservation::class);

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturn($reservation);
    $reservationRepo->shouldReceive('findConflicting')->andReturn([$conflicting]);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ApproveReservation($reservationRepo, $eventDispatcher);

    $useCase->execute($reservation->id()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'conflicting reservation');

test('throws INVALID_STATUS_TRANSITION when reservation is already confirmed', function () {
    $reservation = createPendingReservation();
    $reservation->approve(Uuid::generate()); // now confirmed
    $reservation->pullDomainEvents();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturn($reservation);
    $reservationRepo->shouldReceive('findConflicting')->andReturn([]);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ApproveReservation($reservationRepo, $eventDispatcher);

    $useCase->execute($reservation->id()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'Cannot transition');
