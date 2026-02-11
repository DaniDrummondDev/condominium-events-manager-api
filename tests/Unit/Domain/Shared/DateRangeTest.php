<?php

declare(strict_types=1);

use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\DateRange;

test('creates a valid date range', function () {
    $start = new DateTimeImmutable('2026-03-01 10:00:00');
    $end = new DateTimeImmutable('2026-03-01 12:00:00');

    $range = new DateRange($start, $end);

    expect($range->start())->toBe($start)
        ->and($range->end())->toBe($end);
});

test('throws when start equals end', function () {
    $date = new DateTimeImmutable('2026-03-01 10:00:00');

    new DateRange($date, $date);
})->throws(DomainException::class, 'Start date must be before end date');

test('throws when start is after end', function () {
    $start = new DateTimeImmutable('2026-03-01 14:00:00');
    $end = new DateTimeImmutable('2026-03-01 10:00:00');

    new DateRange($start, $end);
})->throws(DomainException::class, 'Start date must be before end date');

test('detects overlapping ranges', function () {
    $range1 = new DateRange(
        new DateTimeImmutable('2026-03-01 10:00:00'),
        new DateTimeImmutable('2026-03-01 14:00:00'),
    );

    $range2 = new DateRange(
        new DateTimeImmutable('2026-03-01 12:00:00'),
        new DateTimeImmutable('2026-03-01 16:00:00'),
    );

    expect($range1->overlaps($range2))->toBeTrue()
        ->and($range2->overlaps($range1))->toBeTrue();
});

test('detects non-overlapping ranges', function () {
    $range1 = new DateRange(
        new DateTimeImmutable('2026-03-01 10:00:00'),
        new DateTimeImmutable('2026-03-01 12:00:00'),
    );

    $range2 = new DateRange(
        new DateTimeImmutable('2026-03-01 14:00:00'),
        new DateTimeImmutable('2026-03-01 16:00:00'),
    );

    expect($range1->overlaps($range2))->toBeFalse();
});

test('adjacent ranges do not overlap', function () {
    $range1 = new DateRange(
        new DateTimeImmutable('2026-03-01 10:00:00'),
        new DateTimeImmutable('2026-03-01 12:00:00'),
    );

    $range2 = new DateRange(
        new DateTimeImmutable('2026-03-01 12:00:00'),
        new DateTimeImmutable('2026-03-01 14:00:00'),
    );

    expect($range1->overlaps($range2))->toBeFalse();
});

test('contains a datetime within range', function () {
    $range = new DateRange(
        new DateTimeImmutable('2026-03-01 10:00:00'),
        new DateTimeImmutable('2026-03-01 14:00:00'),
    );

    expect($range->contains(new DateTimeImmutable('2026-03-01 12:00:00')))->toBeTrue()
        ->and($range->contains(new DateTimeImmutable('2026-03-01 10:00:00')))->toBeTrue()
        ->and($range->contains(new DateTimeImmutable('2026-03-01 14:00:00')))->toBeFalse()
        ->and($range->contains(new DateTimeImmutable('2026-03-01 09:00:00')))->toBeFalse();
});

test('calculates duration in minutes', function () {
    $range = new DateRange(
        new DateTimeImmutable('2026-03-01 10:00:00'),
        new DateTimeImmutable('2026-03-01 12:30:00'),
    );

    expect($range->durationInMinutes())->toBe(150);
});

test('equals works correctly', function () {
    $range1 = new DateRange(
        new DateTimeImmutable('2026-03-01 10:00:00'),
        new DateTimeImmutable('2026-03-01 12:00:00'),
    );

    $range2 = new DateRange(
        new DateTimeImmutable('2026-03-01 10:00:00'),
        new DateTimeImmutable('2026-03-01 12:00:00'),
    );

    $range3 = new DateRange(
        new DateTimeImmutable('2026-03-01 10:00:00'),
        new DateTimeImmutable('2026-03-01 14:00:00'),
    );

    expect($range1->equals($range2))->toBeTrue()
        ->and($range1->equals($range3))->toBeFalse();
});

test('converts to string', function () {
    $range = new DateRange(
        new DateTimeImmutable('2026-03-01 10:00:00'),
        new DateTimeImmutable('2026-03-01 14:30:00'),
    );

    expect((string) $range)->toBe('2026-03-01 10:00 — 2026-03-01 14:30');
});
