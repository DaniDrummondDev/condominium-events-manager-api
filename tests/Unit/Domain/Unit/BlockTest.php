<?php

declare(strict_types=1);

use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Block;
use Domain\Unit\Enums\BlockStatus;
use Domain\Unit\Events\BlockCreated;

// --- Factory method ---

test('create() factory creates block with Active status', function () {
    $id = Uuid::generate();
    $tenantId = Uuid::generate()->value();

    $block = Block::create($id, 'Bloco A', 'A', 10, $tenantId);

    expect($block->id())->toBe($id)
        ->and($block->name())->toBe('Bloco A')
        ->and($block->identifier())->toBe('A')
        ->and($block->floors())->toBe(10)
        ->and($block->status())->toBe(BlockStatus::Active)
        ->and($block->isActive())->toBeTrue()
        ->and($block->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('create() factory emits BlockCreated event', function () {
    $id = Uuid::generate();
    $tenantId = Uuid::generate()->value();

    $block = Block::create($id, 'Bloco B', 'B', 5, $tenantId);
    $events = $block->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(BlockCreated::class)
        ->and($events[0]->blockId)->toBe($id->value())
        ->and($events[0]->tenantId)->toBe($tenantId);
});

test('create() with null floors', function () {
    $id = Uuid::generate();

    $block = Block::create($id, 'Bloco C', 'C', null, Uuid::generate()->value());

    expect($block->floors())->toBeNull();
});

// --- Property updates ---

test('rename() updates name', function () {
    $block = Block::create(Uuid::generate(), 'Bloco A', 'A', 10, Uuid::generate()->value());
    $block->pullDomainEvents();

    $block->rename('Bloco Alpha');

    expect($block->name())->toBe('Bloco Alpha');
});

test('updateIdentifier() updates identifier', function () {
    $block = Block::create(Uuid::generate(), 'Bloco A', 'A', 10, Uuid::generate()->value());
    $block->pullDomainEvents();

    $block->updateIdentifier('ALPHA');

    expect($block->identifier())->toBe('ALPHA');
});

test('updateFloors() updates floors', function () {
    $block = Block::create(Uuid::generate(), 'Bloco A', 'A', 10, Uuid::generate()->value());
    $block->pullDomainEvents();

    $block->updateFloors(15);

    expect($block->floors())->toBe(15);
});

test('updateFloors() accepts null', function () {
    $block = Block::create(Uuid::generate(), 'Bloco A', 'A', 10, Uuid::generate()->value());
    $block->pullDomainEvents();

    $block->updateFloors(null);

    expect($block->floors())->toBeNull();
});

// --- Status changes ---

test('activate() sets status to Active', function () {
    $block = Block::create(Uuid::generate(), 'Bloco A', 'A', 10, Uuid::generate()->value());
    $block->pullDomainEvents();

    $block->deactivate();
    $block->activate();

    expect($block->status())->toBe(BlockStatus::Active)
        ->and($block->isActive())->toBeTrue();
});

test('deactivate() sets status to Inactive', function () {
    $block = Block::create(Uuid::generate(), 'Bloco A', 'A', 10, Uuid::generate()->value());
    $block->pullDomainEvents();

    $block->deactivate();

    expect($block->status())->toBe(BlockStatus::Inactive)
        ->and($block->isActive())->toBeFalse();
});

test('deactivate() is idempotent when already Inactive', function () {
    $block = Block::create(Uuid::generate(), 'Bloco A', 'A', 10, Uuid::generate()->value());
    $block->pullDomainEvents();

    $block->deactivate();
    $block->deactivate();

    expect($block->status())->toBe(BlockStatus::Inactive);
});

// --- isActive ---

test('isActive() returns true when Active', function () {
    $block = Block::create(Uuid::generate(), 'Bloco A', 'A', 10, Uuid::generate()->value());

    expect($block->isActive())->toBeTrue();
});

test('isActive() returns false when Inactive', function () {
    $block = Block::create(Uuid::generate(), 'Bloco A', 'A', 10, Uuid::generate()->value());
    $block->deactivate();

    expect($block->isActive())->toBeFalse();
});

// --- Domain events ---

test('pullDomainEvents() returns and clears events', function () {
    $block = Block::create(Uuid::generate(), 'Bloco A', 'A', 10, Uuid::generate()->value());

    $events = $block->pullDomainEvents();
    expect($events)->toHaveCount(1);

    $eventsAfterPull = $block->pullDomainEvents();
    expect($eventsAfterPull)->toBeEmpty();
});
