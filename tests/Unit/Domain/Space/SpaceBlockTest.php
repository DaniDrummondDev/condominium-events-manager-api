<?php

declare(strict_types=1);

use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceBlock;

// --- Factory method ---

test('create() sets all properties correctly', function () {
    $id = Uuid::generate();
    $spaceId = Uuid::generate();
    $blockedBy = Uuid::generate();
    $start = new DateTimeImmutable('2025-06-01 08:00:00');
    $end = new DateTimeImmutable('2025-06-01 18:00:00');

    $block = SpaceBlock::create($id, $spaceId, 'maintenance', $start, $end, $blockedBy, 'Pintura do salão');

    expect($block->id())->toBe($id)
        ->and($block->spaceId())->toBe($spaceId)
        ->and($block->reason())->toBe('maintenance')
        ->and($block->startDatetime())->toBe($start)
        ->and($block->endDatetime())->toBe($end)
        ->and($block->blockedBy())->toBe($blockedBy)
        ->and($block->notes())->toBe('Pintura do salão')
        ->and($block->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('create() with null notes', function () {
    $block = SpaceBlock::create(
        Uuid::generate(),
        Uuid::generate(),
        'holiday',
        new DateTimeImmutable('2025-12-25 00:00:00'),
        new DateTimeImmutable('2025-12-26 00:00:00'),
        Uuid::generate(),
        null,
    );

    expect($block->notes())->toBeNull();
});

// --- Validation ---

test('create() throws DomainException when end equals start', function () {
    $spaceId = Uuid::generate();
    $sameDate = new DateTimeImmutable('2025-06-01 08:00:00');

    try {
        SpaceBlock::create(
            Uuid::generate(),
            $spaceId,
            'maintenance',
            $sameDate,
            $sameDate,
            Uuid::generate(),
            null,
        );
        test()->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SPACE_BLOCK_INVALID_PERIOD')
            ->and($e->context())->toHaveKey('space_id')
            ->and($e->context()['space_id'])->toBe($spaceId->value());
    }
});

test('create() throws DomainException when end is before start', function () {
    $spaceId = Uuid::generate();

    try {
        SpaceBlock::create(
            Uuid::generate(),
            $spaceId,
            'administrative',
            new DateTimeImmutable('2025-06-02 08:00:00'),
            new DateTimeImmutable('2025-06-01 08:00:00'),
            Uuid::generate(),
            null,
        );
        test()->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SPACE_BLOCK_INVALID_PERIOD')
            ->and($e->context())->toHaveKey('space_id')
            ->and($e->context())->toHaveKey('start_datetime')
            ->and($e->context())->toHaveKey('end_datetime');
    }
});
