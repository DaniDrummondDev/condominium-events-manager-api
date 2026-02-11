<?php

declare(strict_types=1);

use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceAvailability;

// --- Factory method ---

test('create() sets all properties correctly', function () {
    $id = Uuid::generate();
    $spaceId = Uuid::generate();

    $availability = SpaceAvailability::create($id, $spaceId, 1, '08:00', '18:00');

    expect($availability->id())->toBe($id)
        ->and($availability->spaceId())->toBe($spaceId)
        ->and($availability->dayOfWeek())->toBe(1)
        ->and($availability->startTime())->toBe('08:00')
        ->and($availability->endTime())->toBe('18:00');
});

// --- overlaps ---

test('overlaps() returns true when windows overlap on same day', function () {
    $spaceId = Uuid::generate();

    $a = SpaceAvailability::create(Uuid::generate(), $spaceId, 1, '08:00', '12:00');
    $b = SpaceAvailability::create(Uuid::generate(), $spaceId, 1, '10:00', '14:00');

    expect($a->overlaps($b))->toBeTrue()
        ->and($b->overlaps($a))->toBeTrue();
});

test('overlaps() returns true when one window contains the other', function () {
    $spaceId = Uuid::generate();

    $a = SpaceAvailability::create(Uuid::generate(), $spaceId, 3, '08:00', '18:00');
    $b = SpaceAvailability::create(Uuid::generate(), $spaceId, 3, '10:00', '14:00');

    expect($a->overlaps($b))->toBeTrue()
        ->and($b->overlaps($a))->toBeTrue();
});

test('overlaps() returns false when windows do not overlap on same day', function () {
    $spaceId = Uuid::generate();

    $a = SpaceAvailability::create(Uuid::generate(), $spaceId, 2, '08:00', '10:00');
    $b = SpaceAvailability::create(Uuid::generate(), $spaceId, 2, '10:00', '12:00');

    expect($a->overlaps($b))->toBeFalse()
        ->and($b->overlaps($a))->toBeFalse();
});

test('overlaps() returns false for different days', function () {
    $spaceId = Uuid::generate();

    $a = SpaceAvailability::create(Uuid::generate(), $spaceId, 1, '08:00', '12:00');
    $b = SpaceAvailability::create(Uuid::generate(), $spaceId, 2, '08:00', '12:00');

    expect($a->overlaps($b))->toBeFalse()
        ->and($b->overlaps($a))->toBeFalse();
});

// --- updateTimes ---

test('updateTimes() changes start and end times', function () {
    $availability = SpaceAvailability::create(Uuid::generate(), Uuid::generate(), 1, '08:00', '12:00');

    $availability->updateTimes('09:00', '17:00');

    expect($availability->startTime())->toBe('09:00')
        ->and($availability->endTime())->toBe('17:00');
});
