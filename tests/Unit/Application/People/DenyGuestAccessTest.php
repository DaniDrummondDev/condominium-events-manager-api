<?php

declare(strict_types=1);

use Application\People\Contracts\GuestRepositoryInterface;
use Application\People\DTOs\DenyGuestDTO;
use Application\People\DTOs\GuestDTO;
use Application\People\UseCases\DenyGuestAccess;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\People\Entities\Guest;
use Domain\People\Events\GuestAccessDenied;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

test('denies access for a registered guest', function () {
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
    $deniedBy = Uuid::generate();

    $guestRepo = Mockery::mock(GuestRepositoryInterface::class);
    $guestRepo->shouldReceive('findById')->once()->andReturn($guest);
    $guestRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof GuestAccessDenied);

    $useCase = new DenyGuestAccess($guestRepo, $eventDispatcher);
    $result = $useCase->execute(new DenyGuestDTO(
        guestId: $guest->id()->value(),
        deniedBy: $deniedBy->value(),
        reason: 'Documento inválido',
    ));

    expect($result)->toBeInstanceOf(GuestDTO::class)
        ->and($result->status)->toBe('denied')
        ->and($result->deniedBy)->toBe($deniedBy->value())
        ->and($result->deniedReason)->toBe('Documento inválido');
});

test('throws GUEST_NOT_FOUND when guest does not exist', function () {
    $guestRepo = Mockery::mock(GuestRepositoryInterface::class);
    $guestRepo->shouldReceive('findById')->andReturnNull();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new DenyGuestAccess($guestRepo, $eventDispatcher);

    $useCase->execute(new DenyGuestDTO(
        guestId: Uuid::generate()->value(),
        deniedBy: Uuid::generate()->value(),
        reason: 'Motivo',
    ));
})->throws(DomainException::class, 'Guest not found');

test('throws INVALID_STATUS_TRANSITION when denying checked-in guest', function () {
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
    $guest->checkIn(Uuid::generate());
    $guest->pullDomainEvents();

    $guestRepo = Mockery::mock(GuestRepositoryInterface::class);
    $guestRepo->shouldReceive('findById')->andReturn($guest);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new DenyGuestAccess($guestRepo, $eventDispatcher);

    $useCase->execute(new DenyGuestDTO(
        guestId: $guest->id()->value(),
        deniedBy: Uuid::generate()->value(),
        reason: 'Motivo',
    ));
})->throws(DomainException::class, 'Cannot transition');
