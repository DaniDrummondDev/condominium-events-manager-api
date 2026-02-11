<?php

declare(strict_types=1);

use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\UseCases\DeactivateSpace;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Enums\SpaceType;
use Domain\Space\Events\SpaceDeactivated;

afterEach(fn () => Mockery::close());

test('deactivates space', function () {
    $spaceId = Uuid::generate();
    $space = Space::create(
        $spaceId,
        'Churrasqueira',
        null,
        SpaceType::Bbq,
        20,
        false,
        4,
        30,
        24,
        12,
    );
    $space->pullDomainEvents();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);
    $spaceRepo->shouldReceive('save')
        ->once()
        ->withArgs(fn (Space $s) => $s->status()->value === 'inactive');

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(function (array $events) {
            return count($events) === 1 && $events[0] instanceof SpaceDeactivated;
        });

    $useCase = new DeactivateSpace($spaceRepo, $eventDispatcher);
    $useCase->execute($spaceId->value());
});

test('throws SPACE_NOT_FOUND when space does not exist', function () {
    $spaceId = Uuid::generate();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturnNull();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new DeactivateSpace($spaceRepo, $eventDispatcher);

    try {
        $useCase->execute($spaceId->value());
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SPACE_NOT_FOUND')
            ->and($e->context())->toHaveKey('space_id', $spaceId->value());
    }
});
