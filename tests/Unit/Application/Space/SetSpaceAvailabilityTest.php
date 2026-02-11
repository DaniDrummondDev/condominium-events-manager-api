<?php

declare(strict_types=1);

use Application\Space\Contracts\SpaceAvailabilityRepositoryInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\DTOs\SetSpaceAvailabilityDTO;
use Application\Space\DTOs\SpaceAvailabilityDTO;
use Application\Space\UseCases\SetSpaceAvailability;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Entities\SpaceAvailability;
use Domain\Space\Enums\SpaceType;

afterEach(fn () => Mockery::close());

test('creates availability window', function () {
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

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceIdAndDay')->andReturn([]);
    $availabilityRepo->shouldReceive('save')->once();

    $useCase = new SetSpaceAvailability($spaceRepo, $availabilityRepo);
    $dto = new SetSpaceAvailabilityDTO(
        spaceId: $spaceId->value(),
        dayOfWeek: 1,
        startTime: '08:00',
        endTime: '18:00',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(SpaceAvailabilityDTO::class)
        ->and($result->spaceId)->toBe($spaceId->value())
        ->and($result->dayOfWeek)->toBe(1)
        ->and($result->startTime)->toBe('08:00')
        ->and($result->endTime)->toBe('18:00');
});

test('throws SPACE_NOT_FOUND when space does not exist', function () {
    $spaceId = Uuid::generate();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturnNull();

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);

    $useCase = new SetSpaceAvailability($spaceRepo, $availabilityRepo);
    $dto = new SetSpaceAvailabilityDTO(
        spaceId: $spaceId->value(),
        dayOfWeek: 1,
        startTime: '08:00',
        endTime: '18:00',
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SPACE_NOT_FOUND')
            ->and($e->context())->toHaveKey('space_id', $spaceId->value());
    }
});

test('throws AVAILABILITY_OVERLAP when window overlaps existing', function () {
    $spaceId = Uuid::generate();
    $space = Space::create(
        $spaceId,
        'Piscina',
        null,
        SpaceType::Pool,
        50,
        true,
        null,
        14,
        48,
        24,
    );
    $space->pullDomainEvents();

    $existingAvailability = SpaceAvailability::create(
        Uuid::generate(),
        $spaceId,
        1,
        '08:00',
        '12:00',
    );

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findBySpaceIdAndDay')->andReturn([$existingAvailability]);

    $useCase = new SetSpaceAvailability($spaceRepo, $availabilityRepo);
    $dto = new SetSpaceAvailabilityDTO(
        spaceId: $spaceId->value(),
        dayOfWeek: 1,
        startTime: '10:00',
        endTime: '14:00',
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('AVAILABILITY_OVERLAP')
            ->and($e->context())->toHaveKey('space_id', $spaceId->value())
            ->and($e->context())->toHaveKey('day_of_week', 1);
    }
});
