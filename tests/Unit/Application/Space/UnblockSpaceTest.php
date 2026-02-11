<?php

declare(strict_types=1);

use Application\Space\Contracts\SpaceBlockRepositoryInterface;
use Application\Space\UseCases\UnblockSpace;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceBlock;

afterEach(fn () => Mockery::close());

test('deletes block', function () {
    $blockId = Uuid::generate();
    $spaceId = Uuid::generate();
    $blockedBy = Uuid::generate();

    $block = SpaceBlock::create(
        $blockId,
        $spaceId,
        'maintenance',
        new DateTimeImmutable('2026-03-01T08:00:00+00:00'),
        new DateTimeImmutable('2026-03-01T18:00:00+00:00'),
        $blockedBy,
        null,
    );

    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findById')->andReturn($block);
    $blockRepo->shouldReceive('delete')->once();

    $useCase = new UnblockSpace($blockRepo);
    $useCase->execute($blockId->value());
});

test('throws SPACE_BLOCK_NOT_FOUND when not found', function () {
    $blockId = Uuid::generate();

    $blockRepo = Mockery::mock(SpaceBlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findById')->andReturnNull();

    $useCase = new UnblockSpace($blockRepo);

    try {
        $useCase->execute($blockId->value());
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SPACE_BLOCK_NOT_FOUND')
            ->and($e->context())->toHaveKey('block_id', $blockId->value());
    }
});
