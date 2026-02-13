<?php

declare(strict_types=1);

use Application\People\Contracts\ServiceProviderVisitRepositoryInterface;
use Application\People\DTOs\ServiceProviderVisitDTO;
use Application\People\UseCases\CheckInServiceProvider;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\People\Entities\ServiceProviderVisit;
use Domain\People\Events\ServiceProviderCheckedIn;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createScheduledVisitForCheckIn(): ServiceProviderVisit
{
    return ServiceProviderVisit::create(
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        null,
        new DateTimeImmutable('+1 day'),
        'Manutenção',
        null,
    );
}

test('checks in a scheduled service provider visit', function () {
    $visit = createScheduledVisitForCheckIn();
    $checkedInBy = Uuid::generate();

    $visitRepo = Mockery::mock(ServiceProviderVisitRepositoryInterface::class);
    $visitRepo->shouldReceive('findById')->once()->andReturn($visit);
    $visitRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof ServiceProviderCheckedIn);

    $useCase = new CheckInServiceProvider($visitRepo, $eventDispatcher);
    $result = $useCase->execute($visit->id()->value(), $checkedInBy->value());

    expect($result)->toBeInstanceOf(ServiceProviderVisitDTO::class)
        ->and($result->status)->toBe('checked_in')
        ->and($result->checkedInBy)->toBe($checkedInBy->value());
});

test('throws VISIT_NOT_FOUND when visit does not exist', function () {
    $visitRepo = Mockery::mock(ServiceProviderVisitRepositoryInterface::class);
    $visitRepo->shouldReceive('findById')->andReturnNull();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CheckInServiceProvider($visitRepo, $eventDispatcher);

    $useCase->execute(Uuid::generate()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'Service provider visit not found');

test('throws INVALID_STATUS_TRANSITION when checking in canceled visit', function () {
    $visit = createScheduledVisitForCheckIn();
    $visit->cancel();

    $visitRepo = Mockery::mock(ServiceProviderVisitRepositoryInterface::class);
    $visitRepo->shouldReceive('findById')->andReturn($visit);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CheckInServiceProvider($visitRepo, $eventDispatcher);

    $useCase->execute($visit->id()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'Cannot transition');
