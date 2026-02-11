<?php

declare(strict_types=1);

use Domain\Billing\Enums\InvoiceStatus;

// --- Allowed transitions ---

test('Draft allows only Open', function () {
    $allowed = InvoiceStatus::Draft->allowedTransitions();

    expect($allowed)->toContain(InvoiceStatus::Open)
        ->toHaveCount(1);
});

test('Open allows Paid, PastDue, and Void', function () {
    $allowed = InvoiceStatus::Open->allowedTransitions();

    expect($allowed)->toContain(InvoiceStatus::Paid)
        ->toContain(InvoiceStatus::PastDue)
        ->toContain(InvoiceStatus::Void)
        ->toHaveCount(3);
});

test('PastDue allows Paid and Uncollectible', function () {
    $allowed = InvoiceStatus::PastDue->allowedTransitions();

    expect($allowed)->toContain(InvoiceStatus::Paid)
        ->toContain(InvoiceStatus::Uncollectible)
        ->toHaveCount(2);
});

test('Paid allows no transitions', function () {
    $allowed = InvoiceStatus::Paid->allowedTransitions();

    expect($allowed)->toBeEmpty();
});

test('Void allows no transitions', function () {
    $allowed = InvoiceStatus::Void->allowedTransitions();

    expect($allowed)->toBeEmpty();
});

test('Uncollectible allows no transitions', function () {
    $allowed = InvoiceStatus::Uncollectible->allowedTransitions();

    expect($allowed)->toBeEmpty();
});

// --- canTransitionTo ---

test('canTransitionTo returns true for valid transitions', function () {
    expect(InvoiceStatus::Draft->canTransitionTo(InvoiceStatus::Open))->toBeTrue()
        ->and(InvoiceStatus::Open->canTransitionTo(InvoiceStatus::Paid))->toBeTrue()
        ->and(InvoiceStatus::Open->canTransitionTo(InvoiceStatus::PastDue))->toBeTrue()
        ->and(InvoiceStatus::Open->canTransitionTo(InvoiceStatus::Void))->toBeTrue()
        ->and(InvoiceStatus::PastDue->canTransitionTo(InvoiceStatus::Paid))->toBeTrue()
        ->and(InvoiceStatus::PastDue->canTransitionTo(InvoiceStatus::Uncollectible))->toBeTrue();
});

test('canTransitionTo returns false for invalid transitions', function () {
    expect(InvoiceStatus::Draft->canTransitionTo(InvoiceStatus::Paid))->toBeFalse()
        ->and(InvoiceStatus::Draft->canTransitionTo(InvoiceStatus::PastDue))->toBeFalse()
        ->and(InvoiceStatus::Open->canTransitionTo(InvoiceStatus::Draft))->toBeFalse()
        ->and(InvoiceStatus::Paid->canTransitionTo(InvoiceStatus::Open))->toBeFalse()
        ->and(InvoiceStatus::Void->canTransitionTo(InvoiceStatus::Draft))->toBeFalse()
        ->and(InvoiceStatus::Uncollectible->canTransitionTo(InvoiceStatus::Paid))->toBeFalse();
});

test('canTransitionTo returns false for same status', function () {
    foreach (InvoiceStatus::cases() as $status) {
        expect($status->canTransitionTo($status))->toBeFalse(
            "Expected {$status->value} cannot transition to itself",
        );
    }
});

// --- isFinal ---

test('Paid, Void, and Uncollectible are final', function () {
    expect(InvoiceStatus::Paid->isFinal())->toBeTrue()
        ->and(InvoiceStatus::Void->isFinal())->toBeTrue()
        ->and(InvoiceStatus::Uncollectible->isFinal())->toBeTrue();
});

test('Draft, Open, and PastDue are not final', function () {
    expect(InvoiceStatus::Draft->isFinal())->toBeFalse()
        ->and(InvoiceStatus::Open->isFinal())->toBeFalse()
        ->and(InvoiceStatus::PastDue->isFinal())->toBeFalse();
});

// --- isPayable ---

test('Open and PastDue are payable', function () {
    expect(InvoiceStatus::Open->isPayable())->toBeTrue()
        ->and(InvoiceStatus::PastDue->isPayable())->toBeTrue();
});

test('Draft, Paid, Void, and Uncollectible are not payable', function () {
    expect(InvoiceStatus::Draft->isPayable())->toBeFalse()
        ->and(InvoiceStatus::Paid->isPayable())->toBeFalse()
        ->and(InvoiceStatus::Void->isPayable())->toBeFalse()
        ->and(InvoiceStatus::Uncollectible->isPayable())->toBeFalse();
});
