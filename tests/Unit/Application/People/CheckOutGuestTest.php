<?php

declare(strict_types=1);

use Application\People\Contracts\GuestRepositoryInterface;
use Application\People\DTOs\GuestDTO;
use Application\People\UseCases\CheckOutGuest;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\People\Entities\Guest;
use Domain\People\Events\GuestCheckedOut;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createCheckedInGuestForCheckOut(): Guest
{
    $guest = Guest::create(
        Uuid::generate(),
        Uuid::generate(),
        'João',
        '12345678900',
        null,
        null,
        null,
        Uuid::generate(),
    );
    $guest->checkIn(Uuid::generate());
    $guest->pullDomainEvents();

    return $guest;
}

test('checks out a checked-in guest', function () {
    $guest = createCheckedInGuestForCheckOut();
    $checkedOutBy = Uuid::generate();

    $guestRepo = Mockery::mock(GuestRepositoryInterface::class);
    $guestRepo->shouldReceive('findById')->once()->andReturn($guest);
    $guestRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof GuestCheckedOut);

    $useCase = new CheckOutGuest($guestRepo, $eventDispatcher);
    $result = $useCase->execute($guest->id()->value(), $checkedOutBy->value());

    expect($result)->toBeInstanceOf(GuestDTO::class)
        ->and($result->status)->toBe('checked_out');
});

test('throws GUEST_NOT_FOUND when guest does not exist', function () {
    $guestRepo = Mockery::mock(GuestRepositoryInterface::class);
    $guestRepo->shouldReceive('findById')->andReturnNull();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CheckOutGuest($guestRepo, $eventDispatcher);

    $useCase->execute(Uuid::generate()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'Guest not found');

test('throws INVALID_STATUS_TRANSITION when checking out registered guest', function () {
    $guest = Guest::create(
        Uuid::generate(),
        Uuid::generate(),
        'João',
        null,
        null,
        null,
        null,
        Uuid::generate(),
    );

    $guestRepo = Mockery::mock(GuestRepositoryInterface::class);
    $guestRepo->shouldReceive('findById')->andReturn($guest);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CheckOutGuest($guestRepo, $eventDispatcher);

    $useCase->execute($guest->id()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'Cannot transition');
