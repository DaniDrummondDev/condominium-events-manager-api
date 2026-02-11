<?php

declare(strict_types=1);

use Domain\Billing\Enums\BillingCycle;
use Domain\Billing\ValueObjects\BillingPeriod;
use Domain\Shared\Exceptions\DomainException;

// --- Validation ---

test('creates billing period with valid start and end', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-02-01');
    $period = new BillingPeriod($start, $end);

    expect($period->start())->toBe($start)
        ->and($period->end())->toBe($end);
});

test('throws when end equals start', function () {
    $date = new DateTimeImmutable('2025-01-01');

    new BillingPeriod($date, $date);
})->throws(DomainException::class, 'Billing period end must be after start');

test('throws when end is before start', function () {
    $start = new DateTimeImmutable('2025-02-01');
    $end = new DateTimeImmutable('2025-01-01');

    new BillingPeriod($start, $end);
})->throws(DomainException::class, 'Billing period end must be after start');

// --- totalDays ---

test('totalDays returns number of days in the period', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-01-31');
    $period = new BillingPeriod($start, $end);

    expect($period->totalDays())->toBe(30);
});

test('totalDays for a yearly period', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2026-01-01');
    $period = new BillingPeriod($start, $end);

    expect($period->totalDays())->toBe(365);
});

// --- daysRemaining ---

test('daysRemaining returns days left in the period', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-01-31');
    $period = new BillingPeriod($start, $end);

    $now = new DateTimeImmutable('2025-01-21');

    expect($period->daysRemaining($now))->toBe(10);
});

test('daysRemaining returns 0 when now equals end', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-01-31');
    $period = new BillingPeriod($start, $end);

    expect($period->daysRemaining($end))->toBe(0);
});

test('daysRemaining returns 0 when now is after end', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-01-31');
    $period = new BillingPeriod($start, $end);

    $now = new DateTimeImmutable('2025-02-15');

    expect($period->daysRemaining($now))->toBe(0);
});

// --- isActive ---

test('isActive returns true when now is within period', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-02-01');
    $period = new BillingPeriod($start, $end);

    $now = new DateTimeImmutable('2025-01-15');

    expect($period->isActive($now))->toBeTrue();
});

test('isActive returns true when now equals start', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-02-01');
    $period = new BillingPeriod($start, $end);

    expect($period->isActive($start))->toBeTrue();
});

test('isActive returns false when now equals end', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-02-01');
    $period = new BillingPeriod($start, $end);

    expect($period->isActive($end))->toBeFalse();
});

test('isActive returns false when now is before start', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-02-01');
    $period = new BillingPeriod($start, $end);

    $now = new DateTimeImmutable('2024-12-31');

    expect($period->isActive($now))->toBeFalse();
});

test('isActive returns false when now is after end', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-02-01');
    $period = new BillingPeriod($start, $end);

    $now = new DateTimeImmutable('2025-03-01');

    expect($period->isActive($now))->toBeFalse();
});

// --- next ---

test('next with Monthly cycle creates period starting at current end', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-02-01');
    $period = new BillingPeriod($start, $end);

    $next = $period->next(BillingCycle::Monthly);

    expect($next->start())->toEqual($end)
        ->and($next->end())->toEqual($end->modify('+1 month'));
});

test('next with Yearly cycle creates period starting at current end', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2026-01-01');
    $period = new BillingPeriod($start, $end);

    $next = $period->next(BillingCycle::Yearly);

    expect($next->start())->toEqual($end)
        ->and($next->end())->toEqual($end->modify('+1 year'));
});

test('next creates a valid BillingPeriod', function () {
    $start = new DateTimeImmutable('2025-06-01');
    $end = new DateTimeImmutable('2025-07-01');
    $period = new BillingPeriod($start, $end);

    $next = $period->next(BillingCycle::Monthly);

    // Should not throw; the next period has end > start
    expect($next->start())->toEqual($end)
        ->and($next->totalDays())->toBeGreaterThan(0);
});

// --- prorataFraction ---

test('prorataFraction returns fraction of remaining days', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-01-31');
    $period = new BillingPeriod($start, $end);

    // totalDays = 30, daysRemaining at Jan 21 = 10
    $now = new DateTimeImmutable('2025-01-21');
    $fraction = $period->prorataFraction($now);

    expect($fraction)->toBe(round(10 / 30, 4));
});

test('prorataFraction returns 0 when period has ended', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-01-31');
    $period = new BillingPeriod($start, $end);

    $now = new DateTimeImmutable('2025-02-15');

    expect($period->prorataFraction($now))->toBe(0.0);
});

test('prorataFraction at start of period returns approximately 1.0', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-01-31');
    $period = new BillingPeriod($start, $end);

    $fraction = $period->prorataFraction($start);

    expect($fraction)->toBe(1.0);
});
