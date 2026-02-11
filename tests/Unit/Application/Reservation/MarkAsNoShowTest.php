<?php

declare(strict_types=1);

use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Reservation\DTOs\ReservationDTO;
use Application\Reservation\UseCases\MarkAsNoShow;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Reservation\Entities\Reservation;
use Domain\Reservation\Events\ReservationNoShow;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createInProgressForNoShow(): Reservation
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

test('marks in-progress reservation as no-show', function () {
    $reservation = createInProgressForNoShow();
    $noShowBy = Uuid::generate();

    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->once()->andReturn($reservation);
    $reservationRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof ReservationNoShow);

    $useCase = new MarkAsNoShow($reservationRepo, $eventDispatcher);
    $result = $useCase->execute($reservation->id()->value(), $noShowBy->value());

    expect($result)->toBeInstanceOf(ReservationDTO::class)
        ->and($result->status)->toBe('no_show')
        ->and($result->noShowBy)->toBe($noShowBy->value())
        ->and($result->noShowAt)->not->toBeNull();
});

test('throws RESERVATION_NOT_FOUND when reservation does not exist', function () {
    $reservationRepo = Mockery::mock(ReservationRepositoryInterface::class);
    $reservationRepo->shouldReceive('findById')->andReturnNull();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new MarkAsNoShow($reservationRepo, $eventDispatcher);

    $useCase->execute(Uuid::generate()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'Reservation not found');

test('throws INVALID_STATUS_TRANSITION when marking pending as no-show', function () {
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

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new MarkAsNoShow($reservationRepo, $eventDispatcher);

    $useCase->execute($reservation->id()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'Cannot transition');

test('throws INVALID_STATUS_TRANSITION when marking confirmed as no-show', function () {
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

    $useCase = new MarkAsNoShow($reservationRepo, $eventDispatcher);

    $useCase->execute($reservation->id()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'Cannot transition');
