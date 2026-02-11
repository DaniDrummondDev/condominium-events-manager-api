<?php

declare(strict_types=1);

use Domain\Billing\Enums\SubscriptionStatus;

// --- Allowed transitions ---

test('Trialing allows Active and Canceled', function () {
    $allowed = SubscriptionStatus::Trialing->allowedTransitions();

    expect($allowed)->toContain(SubscriptionStatus::Active)
        ->toContain(SubscriptionStatus::Canceled)
        ->toHaveCount(2);
});

test('Active allows PastDue and Canceled', function () {
    $allowed = SubscriptionStatus::Active->allowedTransitions();

    expect($allowed)->toContain(SubscriptionStatus::PastDue)
        ->toContain(SubscriptionStatus::Canceled)
        ->toHaveCount(2);
});

test('PastDue allows Active, GracePeriod, and Canceled', function () {
    $allowed = SubscriptionStatus::PastDue->allowedTransitions();

    expect($allowed)->toContain(SubscriptionStatus::Active)
        ->toContain(SubscriptionStatus::GracePeriod)
        ->toContain(SubscriptionStatus::Canceled)
        ->toHaveCount(3);
});

test('GracePeriod allows Active, Suspended, and Canceled', function () {
    $allowed = SubscriptionStatus::GracePeriod->allowedTransitions();

    expect($allowed)->toContain(SubscriptionStatus::Active)
        ->toContain(SubscriptionStatus::Suspended)
        ->toContain(SubscriptionStatus::Canceled)
        ->toHaveCount(3);
});

test('Suspended allows Active and Expired', function () {
    $allowed = SubscriptionStatus::Suspended->allowedTransitions();

    expect($allowed)->toContain(SubscriptionStatus::Active)
        ->toContain(SubscriptionStatus::Expired)
        ->toHaveCount(2);
});

test('Canceled allows only Expired', function () {
    $allowed = SubscriptionStatus::Canceled->allowedTransitions();

    expect($allowed)->toContain(SubscriptionStatus::Expired)
        ->toHaveCount(1);
});

test('Expired allows no transitions', function () {
    $allowed = SubscriptionStatus::Expired->allowedTransitions();

    expect($allowed)->toBeEmpty();
});

// --- canTransitionTo ---

test('canTransitionTo returns true for valid transitions', function () {
    expect(SubscriptionStatus::Trialing->canTransitionTo(SubscriptionStatus::Active))->toBeTrue()
        ->and(SubscriptionStatus::Trialing->canTransitionTo(SubscriptionStatus::Canceled))->toBeTrue()
        ->and(SubscriptionStatus::Active->canTransitionTo(SubscriptionStatus::PastDue))->toBeTrue()
        ->and(SubscriptionStatus::Active->canTransitionTo(SubscriptionStatus::Canceled))->toBeTrue()
        ->and(SubscriptionStatus::PastDue->canTransitionTo(SubscriptionStatus::Active))->toBeTrue()
        ->and(SubscriptionStatus::PastDue->canTransitionTo(SubscriptionStatus::GracePeriod))->toBeTrue()
        ->and(SubscriptionStatus::GracePeriod->canTransitionTo(SubscriptionStatus::Suspended))->toBeTrue()
        ->and(SubscriptionStatus::Suspended->canTransitionTo(SubscriptionStatus::Active))->toBeTrue()
        ->and(SubscriptionStatus::Suspended->canTransitionTo(SubscriptionStatus::Expired))->toBeTrue()
        ->and(SubscriptionStatus::Canceled->canTransitionTo(SubscriptionStatus::Expired))->toBeTrue();
});

test('canTransitionTo returns false for invalid transitions', function () {
    expect(SubscriptionStatus::Trialing->canTransitionTo(SubscriptionStatus::Suspended))->toBeFalse()
        ->and(SubscriptionStatus::Active->canTransitionTo(SubscriptionStatus::Expired))->toBeFalse()
        ->and(SubscriptionStatus::Expired->canTransitionTo(SubscriptionStatus::Active))->toBeFalse()
        ->and(SubscriptionStatus::Canceled->canTransitionTo(SubscriptionStatus::Active))->toBeFalse()
        ->and(SubscriptionStatus::Suspended->canTransitionTo(SubscriptionStatus::Canceled))->toBeFalse();
});

test('canTransitionTo returns false for same status', function () {
    foreach (SubscriptionStatus::cases() as $status) {
        expect($status->canTransitionTo($status))->toBeFalse(
            "Expected {$status->value} cannot transition to itself",
        );
    }
});

// --- isOperational ---

test('Trialing, Active, and PastDue are operational', function () {
    expect(SubscriptionStatus::Trialing->isOperational())->toBeTrue()
        ->and(SubscriptionStatus::Active->isOperational())->toBeTrue()
        ->and(SubscriptionStatus::PastDue->isOperational())->toBeTrue();
});

test('non-operational statuses', function () {
    expect(SubscriptionStatus::GracePeriod->isOperational())->toBeFalse()
        ->and(SubscriptionStatus::Suspended->isOperational())->toBeFalse()
        ->and(SubscriptionStatus::Canceled->isOperational())->toBeFalse()
        ->and(SubscriptionStatus::Expired->isOperational())->toBeFalse();
});

// --- allowsAccess ---

test('Trialing, Active, PastDue, and GracePeriod allow access', function () {
    expect(SubscriptionStatus::Trialing->allowsAccess())->toBeTrue()
        ->and(SubscriptionStatus::Active->allowsAccess())->toBeTrue()
        ->and(SubscriptionStatus::PastDue->allowsAccess())->toBeTrue()
        ->and(SubscriptionStatus::GracePeriod->allowsAccess())->toBeTrue();
});

test('Suspended, Canceled, and Expired do not allow access', function () {
    expect(SubscriptionStatus::Suspended->allowsAccess())->toBeFalse()
        ->and(SubscriptionStatus::Canceled->allowsAccess())->toBeFalse()
        ->and(SubscriptionStatus::Expired->allowsAccess())->toBeFalse();
});
