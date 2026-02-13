<?php

declare(strict_types=1);

use Application\People\Contracts\GuestRepositoryInterface;
use Application\People\DTOs\GuestDTO;
use Application\People\UseCases\CheckInGuest;
use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\People\Entities\Guest;
use Domain\People\Events\GuestCheckedIn;
use Domain\Reservation\Entities\Reservation;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createInProgressReservationForCheckIn(): Reservation
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

function createRegisteredGuestForCheckIn(?Uuid $reservationId = null): Guest
{
    return Guest::create(
        Uuid::generate(),
        $reservationId ?? Uuid::generate(),
        'João',
        '12345678900',
        null,
        null,
        null,
        Uuid::generate(),
    );
}

test('checks in guest for in-progress reservation', function () {
    $reservation = createInProgressReservationForCheckIn();
    $guest = createRegisteredGuestForCheckIn($reservation->id());
    $checkedInBy = Uuid::generate();

    $guestRepo = Mockery::mock(GuestRepositoryInterface::class);
    $guestRepo->shouldReceive('findById')->once()->andReturn($guest);
    $guestRepo->shouldReceive('save')->once();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->once()->andReturn($reservation);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof GuestCheckedIn);

    $useCase = new CheckInGuest($guestRepo, $reservationRepo, $eventDispatcher);
    $result = $useCase->execute($guest->id()->value(), $checkedInBy->value());

    expect($result)->toBeInstanceOf(GuestDTO::class)
        ->and($result->status)->toBe('checked_in')
        ->and($result->checkedInBy)->toBe($checkedInBy->value());
});

test('throws GUEST_NOT_FOUND when guest does not exist', function () {
    $guestRepo = Mockery::mock(GuestRepositoryInterface::class);
    $guestRepo->shouldReceive('findById')->andReturnNull();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CheckInGuest($guestRepo, $reservationRepo, $eventDispatcher);

    $useCase->execute(Uuid::generate()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'Guest not found');

test('throws RESERVATION_NOT_IN_PROGRESS when reservation is confirmed', function () {
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
        false, // confirmed
    );
    $reservation->pullDomainEvents();

    $guest = createRegisteredGuestForCheckIn($reservation->id());

    $guestRepo = Mockery::mock(GuestRepositoryInterface::class);
    $guestRepo->shouldReceive('findById')->andReturn($guest);

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturn($reservation);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CheckInGuest($guestRepo, $reservationRepo, $eventDispatcher);

    $useCase->execute($guest->id()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'Reservation must be in progress');
