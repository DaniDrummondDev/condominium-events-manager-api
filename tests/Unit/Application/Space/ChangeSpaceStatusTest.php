<?php

declare(strict_types=1);

use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\DTOs\SpaceDTO;
use Application\Space\UseCases\ChangeSpaceStatus;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Enums\SpaceType;

afterEach(fn () => Mockery::close());

test('changes space status to maintenance', function () {
    $spaceId = Uuid::generate();
    $space = Space::create(
        $spaceId,
        'Piscina',
        'Piscina principal',
        SpaceType::Pool,
        50,
        true,
        null,
        14,
        48,
        24,
    );
    $space->pullDomainEvents();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);
    $spaceRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new ChangeSpaceStatus($spaceRepo, $eventDispatcher);
    $result = $useCase->execute($spaceId->value(), 'maintenance');

    expect($result)->toBeInstanceOf(SpaceDTO::class)
        ->and($result->status)->toBe('maintenance');
});

test('changes space status to active', function () {
    $spaceId = Uuid::generate();
    $space = Space::create(
        $spaceId,
        'Academia',
        null,
        SpaceType::Gym,
        20,
        false,
        2,
        7,
        12,
        6,
    );
    $space->pullDomainEvents();
    $space->setMaintenance();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);
    $spaceRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new ChangeSpaceStatus($spaceRepo, $eventDispatcher);
    $result = $useCase->execute($spaceId->value(), 'active');

    expect($result)->toBeInstanceOf(SpaceDTO::class)
        ->and($result->status)->toBe('active');
});

test('throws SPACE_NOT_FOUND when space does not exist', function () {
    $spaceId = Uuid::generate();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturnNull();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ChangeSpaceStatus($spaceRepo, $eventDispatcher);

    try {
        $useCase->execute($spaceId->value(), 'maintenance');
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SPACE_NOT_FOUND')
            ->and($e->context())->toHaveKey('space_id', $spaceId->value());
    }
});
