<?php

declare(strict_types=1);

use Application\People\Contracts\GuestRepositoryInterface;
use Application\People\DTOs\GuestDTO;
use Application\People\DTOs\RegisterGuestDTO;
use Application\People\UseCases\RegisterGuest;
use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Domain\Reservation\Entities\Reservation;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;

afterEach(fn () => Mockery::close());

function createConfirmedReservationForGuest(): Reservation
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

function makeRegisterGuestDTO(string $reservationId): RegisterGuestDTO
{
    return new RegisterGuestDTO(
        reservationId: $reservationId,
        name: 'João Silva',
        document: '12345678900',
        phone: '11999999999',
        vehiclePlate: 'ABC1234',
        relationship: 'Amigo',
        registeredBy: Uuid::generate()->value(),
    );
}

test('registers guest for confirmed reservation', function () {
    $reservation = createConfirmedReservationForGuest();
    $dto = makeRegisterGuestDTO($reservation->id()->value());

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->once()->andReturn($reservation);

    $space = Mockery::mock(Space::class);
    $space->shouldReceive('capacity')->andReturn(50);

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->once()->andReturn($space);

    $guestRepo = Mockery::mock(GuestRepositoryInterface::class);
    $guestRepo->shouldReceive('countByReservation')->once()->andReturn(0);
    $guestRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new RegisterGuest($guestRepo, $reservationRepo, $spaceRepo, $eventDispatcher);
    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(GuestDTO::class)
        ->and($result->name)->toBe('João Silva')
        ->and($result->status)->toBe('registered')
        ->and($result->reservationId)->toBe($reservation->id()->value());
});

test('throws RESERVATION_NOT_FOUND when reservation does not exist', function () {
    $dto = makeRegisterGuestDTO(Uuid::generate()->value());

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturnNull();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $guestRepo = Mockery::mock(GuestRepositoryInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new RegisterGuest($guestRepo, $reservationRepo, $spaceRepo, $eventDispatcher);

    $useCase->execute($dto);
})->throws(DomainException::class, 'Reservation not found');

test('throws RESERVATION_NOT_CONFIRMED when reservation is pending', function () {
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

    $dto = makeRegisterGuestDTO($reservation->id()->value());

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturn($reservation);

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $guestRepo = Mockery::mock(GuestRepositoryInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new RegisterGuest($guestRepo, $reservationRepo, $spaceRepo, $eventDispatcher);

    $useCase->execute($dto);
})->throws(DomainException::class, 'Reservation must be confirmed or in progress');

test('throws GUEST_LIMIT_EXCEEDED when space capacity reached', function () {
    $reservation = createConfirmedReservationForGuest();
    $dto = makeRegisterGuestDTO($reservation->id()->value());

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturn($reservation);

    $space = Mockery::mock(Space::class);
    $space->shouldReceive('capacity')->andReturn(5);

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $guestRepo = Mockery::mock(GuestRepositoryInterface::class);
    $guestRepo->shouldReceive('countByReservation')->andReturn(5);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new RegisterGuest($guestRepo, $reservationRepo, $spaceRepo, $eventDispatcher);

    $useCase->execute($dto);
})->throws(DomainException::class, 'Guest limit for this space has been reached');

test('throws GUEST_LIMIT_EXCEEDED when expected guests reached', function () {
    $reservation = Reservation::create(
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        'Festa',
        new DateTimeImmutable('+1 day 10:00'),
        new DateTimeImmutable('+1 day 14:00'),
        3, // expectedGuests = 3
        null,
        false,
    );
    $reservation->pullDomainEvents();

    $dto = makeRegisterGuestDTO($reservation->id()->value());

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturn($reservation);

    $space = Mockery::mock(Space::class);
    $space->shouldReceive('capacity')->andReturn(50);

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $guestRepo = Mockery::mock(GuestRepositoryInterface::class);
    $guestRepo->shouldReceive('countByReservation')->andReturn(3);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new RegisterGuest($guestRepo, $reservationRepo, $spaceRepo, $eventDispatcher);

    $useCase->execute($dto);
})->throws(DomainException::class, 'Expected guest count for this reservation has been reached');
