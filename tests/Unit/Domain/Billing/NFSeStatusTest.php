<?php

declare(strict_types=1);

use Domain\Billing\Enums\NFSeStatus;

// --- Allowed transitions ---

test('Draft allows only Processing', function () {
    $allowed = NFSeStatus::Draft->allowedTransitions();

    expect($allowed)->toContain(NFSeStatus::Processing)
        ->toHaveCount(1);
});

test('Processing allows Authorized and Denied', function () {
    $allowed = NFSeStatus::Processing->allowedTransitions();

    expect($allowed)->toContain(NFSeStatus::Authorized)
        ->toContain(NFSeStatus::Denied)
        ->toHaveCount(2);
});

test('Authorized allows only Cancelled', function () {
    $allowed = NFSeStatus::Authorized->allowedTransitions();

    expect($allowed)->toContain(NFSeStatus::Cancelled)
        ->toHaveCount(1);
});

test('Denied allows only Draft (retry)', function () {
    $allowed = NFSeStatus::Denied->allowedTransitions();

    expect($allowed)->toContain(NFSeStatus::Draft)
        ->toHaveCount(1);
});

test('Cancelled allows no transitions', function () {
    $allowed = NFSeStatus::Cancelled->allowedTransitions();

    expect($allowed)->toBeEmpty();
});

// --- canTransitionTo ---

test('canTransitionTo returns true for valid transitions', function () {
    expect(NFSeStatus::Draft->canTransitionTo(NFSeStatus::Processing))->toBeTrue()
        ->and(NFSeStatus::Processing->canTransitionTo(NFSeStatus::Authorized))->toBeTrue()
        ->and(NFSeStatus::Processing->canTransitionTo(NFSeStatus::Denied))->toBeTrue()
        ->and(NFSeStatus::Authorized->canTransitionTo(NFSeStatus::Cancelled))->toBeTrue()
        ->and(NFSeStatus::Denied->canTransitionTo(NFSeStatus::Draft))->toBeTrue();
});

test('canTransitionTo returns false for invalid transitions', function () {
    expect(NFSeStatus::Draft->canTransitionTo(NFSeStatus::Authorized))->toBeFalse()
        ->and(NFSeStatus::Draft->canTransitionTo(NFSeStatus::Cancelled))->toBeFalse()
        ->and(NFSeStatus::Processing->canTransitionTo(NFSeStatus::Draft))->toBeFalse()
        ->and(NFSeStatus::Authorized->canTransitionTo(NFSeStatus::Draft))->toBeFalse()
        ->and(NFSeStatus::Cancelled->canTransitionTo(NFSeStatus::Draft))->toBeFalse();
});

test('canTransitionTo returns false for same status', function () {
    foreach (NFSeStatus::cases() as $status) {
        expect($status->canTransitionTo($status))->toBeFalse(
            "Expected {$status->value} cannot transition to itself",
        );
    }
});

// --- isFinal ---

test('Cancelled is final', function () {
    expect(NFSeStatus::Cancelled->isFinal())->toBeTrue();
});

test('Non-cancelled statuses are not final', function () {
    expect(NFSeStatus::Draft->isFinal())->toBeFalse()
        ->and(NFSeStatus::Processing->isFinal())->toBeFalse()
        ->and(NFSeStatus::Authorized->isFinal())->toBeFalse()
        ->and(NFSeStatus::Denied->isFinal())->toBeFalse();
});

// --- canRetry ---

test('Denied can be retried', function () {
    expect(NFSeStatus::Denied->canRetry())->toBeTrue();
});

test('Non-denied statuses cannot be retried', function () {
    expect(NFSeStatus::Draft->canRetry())->toBeFalse()
        ->and(NFSeStatus::Processing->canRetry())->toBeFalse()
        ->and(NFSeStatus::Authorized->canRetry())->toBeFalse()
        ->and(NFSeStatus::Cancelled->canRetry())->toBeFalse();
});
