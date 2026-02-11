<?php

declare(strict_types=1);

use Application\Space\Contracts\SpaceAvailabilityRepositoryInterface;
use Application\Space\UseCases\DeleteSpaceAvailability;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceAvailability;

afterEach(fn () => Mockery::close());

test('deletes availability', function () {
    $availabilityId = Uuid::generate();
    $spaceId = Uuid::generate();

    $availability = SpaceAvailability::create(
        $availabilityId,
        $spaceId,
        1,
        '08:00',
        '18:00',
    );

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findById')->andReturn($availability);
    $availabilityRepo->shouldReceive('delete')->once();

    $useCase = new DeleteSpaceAvailability($availabilityRepo);
    $useCase->execute($availabilityId->value());
});

test('throws AVAILABILITY_NOT_FOUND when not found', function () {
    $availabilityId = Uuid::generate();

    $availabilityRepo = Mockery::mock(SpaceAvailabilityRepositoryInterface::class);
    $availabilityRepo->shouldReceive('findById')->andReturnNull();

    $useCase = new DeleteSpaceAvailability($availabilityRepo);

    try {
        $useCase->execute($availabilityId->value());
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('AVAILABILITY_NOT_FOUND')
            ->and($e->context())->toHaveKey('availability_id', $availabilityId->value());
    }
});
