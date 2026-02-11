<?php

declare(strict_types=1);

use Domain\Billing\Enums\PaymentStatus;

// --- Allowed transitions ---

test('Pending allows Authorized, Paid, Failed, and Canceled', function () {
    $allowed = PaymentStatus::Pending->allowedTransitions();

    expect($allowed)->toContain(PaymentStatus::Authorized)
        ->toContain(PaymentStatus::Paid)
        ->toContain(PaymentStatus::Failed)
        ->toContain(PaymentStatus::Canceled)
        ->toHaveCount(4);
});

test('Authorized allows Paid, Failed, and Canceled', function () {
    $allowed = PaymentStatus::Authorized->allowedTransitions();

    expect($allowed)->toContain(PaymentStatus::Paid)
        ->toContain(PaymentStatus::Failed)
        ->toContain(PaymentStatus::Canceled)
        ->toHaveCount(3);
});

test('Paid allows only Refunded', function () {
    $allowed = PaymentStatus::Paid->allowedTransitions();

    expect($allowed)->toContain(PaymentStatus::Refunded)
        ->toHaveCount(1);
});

test('Failed allows no transitions', function () {
    $allowed = PaymentStatus::Failed->allowedTransitions();

    expect($allowed)->toBeEmpty();
});

test('Canceled allows no transitions', function () {
    $allowed = PaymentStatus::Canceled->allowedTransitions();

    expect($allowed)->toBeEmpty();
});

test('Refunded allows no transitions', function () {
    $allowed = PaymentStatus::Refunded->allowedTransitions();

    expect($allowed)->toBeEmpty();
});

// --- canTransitionTo ---

test('canTransitionTo returns true for valid transitions', function () {
    expect(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Authorized))->toBeTrue()
        ->and(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Paid))->toBeTrue()
        ->and(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Failed))->toBeTrue()
        ->and(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Canceled))->toBeTrue()
        ->and(PaymentStatus::Authorized->canTransitionTo(PaymentStatus::Paid))->toBeTrue()
        ->and(PaymentStatus::Authorized->canTransitionTo(PaymentStatus::Failed))->toBeTrue()
        ->and(PaymentStatus::Authorized->canTransitionTo(PaymentStatus::Canceled))->toBeTrue()
        ->and(PaymentStatus::Paid->canTransitionTo(PaymentStatus::Refunded))->toBeTrue();
});

test('canTransitionTo returns false for invalid transitions', function () {
    expect(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Refunded))->toBeFalse()
        ->and(PaymentStatus::Authorized->canTransitionTo(PaymentStatus::Refunded))->toBeFalse()
        ->and(PaymentStatus::Paid->canTransitionTo(PaymentStatus::Pending))->toBeFalse()
        ->and(PaymentStatus::Failed->canTransitionTo(PaymentStatus::Paid))->toBeFalse()
        ->and(PaymentStatus::Canceled->canTransitionTo(PaymentStatus::Pending))->toBeFalse()
        ->and(PaymentStatus::Refunded->canTransitionTo(PaymentStatus::Paid))->toBeFalse();
});

test('canTransitionTo returns false for same status', function () {
    foreach (PaymentStatus::cases() as $status) {
        expect($status->canTransitionTo($status))->toBeFalse(
            "Expected {$status->value} cannot transition to itself",
        );
    }
});

// --- isSuccessful ---

test('only Paid is successful', function () {
    expect(PaymentStatus::Paid->isSuccessful())->toBeTrue();
});

test('non-Paid statuses are not successful', function () {
    expect(PaymentStatus::Pending->isSuccessful())->toBeFalse()
        ->and(PaymentStatus::Authorized->isSuccessful())->toBeFalse()
        ->and(PaymentStatus::Failed->isSuccessful())->toBeFalse()
        ->and(PaymentStatus::Canceled->isSuccessful())->toBeFalse()
        ->and(PaymentStatus::Refunded->isSuccessful())->toBeFalse();
});
