<?php

declare(strict_types=1);

use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Reservation\DTOs\CancelReservationDTO;
use Application\Reservation\DTOs\ReservationDTO;
use Application\Reservation\UseCases\CancelReservation;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Domain\Reservation\Entities\Reservation;
use Domain\Reservation\Events\ReservationCanceled;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Enums\SpaceType;

afterEach(fn () => Mockery::close());

function createPendingForCancel(): Reservation
{
    $reservation = Reservation::create(
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        'Festa',
        new DateTimeImmutable('+3 days 10:00'),
        new DateTimeImmutable('+3 days 14:00'),
        20,
        null,
        true,
    );
    $reservation->pullDomainEvents();

    return $reservation;
}

function createConfirmedForCancel(): Reservation
{
    $reservation = createPendingForCancel();
    $reservation->approve(Uuid::generate());
    $reservation->pullDomainEvents();

    return $reservation;
}

function createCancelSpace(?Uuid $id = null, int $cancellationDeadlineHours = 24): Space
{
    return Space::create(
        $id ?? Uuid::generate(),
        'Salão',
        null,
        SpaceType::PartyHall,
        50,
        true,
        8,
        30,
        24,
        $cancellationDeadlineHours,
    );
}

test('cancels pending reservation successfully', function () {
    $reservation = createPendingForCancel();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->once()->andReturn($reservation);
    $reservationRepo->shouldReceive('save')->once();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn(createCancelSpace());

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof ReservationCanceled);

    $useCase = new CancelReservation($reservationRepo, $spaceRepo, $eventDispatcher);
    $dto = new CancelReservationDTO(
        reservationId: $reservation->id()->value(),
        canceledBy: Uuid::generate()->value(),
        cancellationReason: 'Mudei de planos',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(ReservationDTO::class)
        ->and($result->status)->toBe('canceled')
        ->and($result->cancellationReason)->toBe('Mudei de planos');
});

test('cancels confirmed reservation successfully', function () {
    $reservation = createConfirmedForCancel();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->once()->andReturn($reservation);
    $reservationRepo->shouldReceive('save')->once();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn(createCancelSpace());

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new CancelReservation($reservationRepo, $spaceRepo, $eventDispatcher);
    $dto = new CancelReservationDTO(
        reservationId: $reservation->id()->value(),
        canceledBy: Uuid::generate()->value(),
        cancellationReason: 'Imprevisto',
    );

    $result = $useCase->execute($dto);

    expect($result->status)->toBe('canceled');
});

test('late cancellation emits event with isLateCancellation flag', function () {
    // Reservation starts in 2 hours — late cancel with 24h deadline
    $reservation = Reservation::create(
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        'Festa urgente',
        new DateTimeImmutable('+2 hours'),
        new DateTimeImmutable('+6 hours'),
        10,
        null,
        false, // auto-confirmed
    );
    $reservation->pullDomainEvents();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturn($reservation);
    $reservationRepo->shouldReceive('save')->once();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn(createCancelSpace(cancellationDeadlineHours: 24));

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(function (array $events) {
            return count($events) === 1
                && $events[0] instanceof ReservationCanceled
                && $events[0]->isLateCancellation === true;
        });

    $useCase = new CancelReservation($reservationRepo, $spaceRepo, $eventDispatcher);
    $dto = new CancelReservationDTO(
        reservationId: $reservation->id()->value(),
        canceledBy: Uuid::generate()->value(),
        cancellationReason: 'Desistência tardia',
    );

    $useCase->execute($dto);
});

test('throws RESERVATION_NOT_FOUND when reservation does not exist', function () {
    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturnNull();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CancelReservation($reservationRepo, $spaceRepo, $eventDispatcher);
    $dto = new CancelReservationDTO(
        reservationId: Uuid::generate()->value(),
        canceledBy: Uuid::generate()->value(),
        cancellationReason: 'Motivo',
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'Reservation not found');

test('throws INVALID_STATUS_TRANSITION when canceling completed reservation', function () {
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
    $reservation->complete();
    $reservation->pullDomainEvents();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturn($reservation);

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn(createCancelSpace());

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CancelReservation($reservationRepo, $spaceRepo, $eventDispatcher);
    $dto = new CancelReservationDTO(
        reservationId: $reservation->id()->value(),
        canceledBy: Uuid::generate()->value(),
        cancellationReason: 'Motivo',
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'Cannot transition');
