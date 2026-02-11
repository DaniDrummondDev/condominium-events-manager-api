<?php

declare(strict_types=1);

use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Space\Contracts\SpaceBlockRepositoryInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\DTOs\BlockSpaceDTO;
use Application\Space\DTOs\SpaceBlockDTO;
use Application\Space\UseCases\BlockSpace;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Enums\SpaceType;
use Domain\Space\Events\SpaceBlocked;

afterEach(fn () => Mockery::close());

test('creates block and returns SpaceBlockDTO', function () {
    $spaceId = Uuid::generate();
    $blockedBy = Uuid::generate();
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

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $blockRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(function (array $events) {
            return count($events) === 1 && $events[0] instanceof SpaceBlocked;
        });

    $useCase = new BlockSpace($spaceRepo, $blockRepo, $eventDispatcher);
    $dto = new BlockSpaceDTO(
        spaceId: $spaceId->value(),
        reason: 'maintenance',
        startDatetime: '2026-03-01T08:00:00+00:00',
        endDatetime: '2026-03-01T18:00:00+00:00',
        blockedBy: $blockedBy->value(),
        notes: 'Manutencao da piscina',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(SpaceBlockDTO::class)
        ->and($result->spaceId)->toBe($spaceId->value())
        ->and($result->reason)->toBe('maintenance')
        ->and($result->blockedBy)->toBe($blockedBy->value())
        ->and($result->notes)->toBe('Manutencao da piscina');
});

test('throws SPACE_NOT_FOUND when space does not exist', function () {
    $spaceId = Uuid::generate();
    $blockedBy = Uuid::generate();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturnNull();

    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new BlockSpace($spaceRepo, $blockRepo, $eventDispatcher);
    $dto = new BlockSpaceDTO(
        spaceId: $spaceId->value(),
        reason: 'maintenance',
        startDatetime: '2026-03-01T08:00:00+00:00',
        endDatetime: '2026-03-01T18:00:00+00:00',
        blockedBy: $blockedBy->value(),
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SPACE_NOT_FOUND')
            ->and($e->context())->toHaveKey('space_id', $spaceId->value());
    }
});
