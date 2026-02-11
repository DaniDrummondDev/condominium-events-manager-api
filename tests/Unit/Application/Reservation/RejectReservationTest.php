<?php

declare(strict_types=1);

use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Reservation\DTOs\RejectReservationDTO;
use Application\Reservation\DTOs\ReservationDTO;
use Application\Reservation\UseCases\RejectReservation;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Reservation\Entities\Reservation;
use Domain\Reservation\Events\ReservationRejected;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createPendingForReject(): Reservation
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
        true,
    );
    $reservation->pullDomainEvents();

    return $reservation;
}

test('rejects pending reservation and returns ReservationDTO', function () {
    $reservation = createPendingForReject();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->once()->andReturn($reservation);
    $reservationRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof ReservationRejected);

    $useCase = new RejectReservation($reservationRepo, $eventDispatcher);
    $dto = new RejectReservationDTO(
        reservationId: $reservation->id()->value(),
        rejectedBy: Uuid::generate()->value(),
        rejectionReason: 'Horário indisponível',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(ReservationDTO::class)
        ->and($result->status)->toBe('rejected')
        ->and($result->rejectionReason)->toBe('Horário indisponível');
});

test('throws RESERVATION_NOT_FOUND when reservation does not exist', function () {
    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturnNull();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new RejectReservation($reservationRepo, $eventDispatcher);
    $dto = new RejectReservationDTO(
        reservationId: Uuid::generate()->value(),
        rejectedBy: Uuid::generate()->value(),
        rejectionReason: 'Motivo',
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'Reservation not found');

test('throws INVALID_STATUS_TRANSITION when rejecting confirmed reservation', function () {
    $reservation = createPendingForReject();
    $reservation->approve(Uuid::generate());
    $reservation->pullDomainEvents();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturn($reservation);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new RejectReservation($reservationRepo, $eventDispatcher);
    $dto = new RejectReservationDTO(
        reservationId: $reservation->id()->value(),
        rejectedBy: Uuid::generate()->value(),
        rejectionReason: 'Motivo tardio',
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'Cannot transition');
