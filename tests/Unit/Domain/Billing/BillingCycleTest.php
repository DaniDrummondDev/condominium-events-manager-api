<?php

declare(strict_types=1);

use Domain\Billing\Enums\BillingCycle;

// --- Enum values ---

test('Monthly has correct value', function () {
    expect(BillingCycle::Monthly->value)->toBe('monthly');
});

test('Yearly has correct value', function () {
    expect(BillingCycle::Yearly->value)->toBe('yearly');
});

test('BillingCycle has exactly 2 cases', function () {
    expect(BillingCycle::cases())->toHaveCount(2);
});

// --- periodDays ---

test('Monthly has 30 days', function () {
    expect(BillingCycle::Monthly->periodDays())->toBe(30);
});

test('Yearly has 365 days', function () {
    expect(BillingCycle::Yearly->periodDays())->toBe(365);
});

// --- label ---

test('Monthly label is Mensal', function () {
    expect(BillingCycle::Monthly->label())->toBe('Mensal');
});

test('Yearly label is Anual', function () {
    expect(BillingCycle::Yearly->label())->toBe('Anual');
});
