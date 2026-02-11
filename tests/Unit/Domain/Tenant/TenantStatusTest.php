<?php

declare(strict_types=1);

use Domain\Tenant\Enums\TenantStatus;

// --- Allowed transitions ---

test('Prospect allows Trial and Provisioning', function () {
    $allowed = TenantStatus::Prospect->allowedTransitions();

    expect($allowed)->toContain(TenantStatus::Trial)
        ->toContain(TenantStatus::Provisioning)
        ->toHaveCount(2);
});

test('Trial allows Provisioning and Canceled', function () {
    $allowed = TenantStatus::Trial->allowedTransitions();

    expect($allowed)->toContain(TenantStatus::Provisioning)
        ->toContain(TenantStatus::Canceled)
        ->toHaveCount(2);
});

test('Provisioning allows Active and Prospect (rollback)', function () {
    $allowed = TenantStatus::Provisioning->allowedTransitions();

    expect($allowed)->toContain(TenantStatus::Active)
        ->toContain(TenantStatus::Prospect)
        ->toHaveCount(2);
});

test('Active allows PastDue, Suspended, and Canceled', function () {
    $allowed = TenantStatus::Active->allowedTransitions();

    expect($allowed)->toContain(TenantStatus::PastDue)
        ->toContain(TenantStatus::Suspended)
        ->toContain(TenantStatus::Canceled)
        ->toHaveCount(3);
});

test('PastDue allows Active, Suspended, and Canceled', function () {
    $allowed = TenantStatus::PastDue->allowedTransitions();

    expect($allowed)->toContain(TenantStatus::Active)
        ->toContain(TenantStatus::Suspended)
        ->toContain(TenantStatus::Canceled)
        ->toHaveCount(3);
});

test('Suspended allows Active and Canceled', function () {
    $allowed = TenantStatus::Suspended->allowedTransitions();

    expect($allowed)->toContain(TenantStatus::Active)
        ->toContain(TenantStatus::Canceled)
        ->toHaveCount(2);
});

test('Canceled allows only Archived', function () {
    $allowed = TenantStatus::Canceled->allowedTransitions();

    expect($allowed)->toContain(TenantStatus::Archived)
        ->toHaveCount(1);
});

test('Archived allows no transitions', function () {
    $allowed = TenantStatus::Archived->allowedTransitions();

    expect($allowed)->toBeEmpty();
});

// --- canTransitionTo ---

test('canTransitionTo returns true for valid transition', function () {
    expect(TenantStatus::Prospect->canTransitionTo(TenantStatus::Provisioning))->toBeTrue()
        ->and(TenantStatus::Active->canTransitionTo(TenantStatus::Suspended))->toBeTrue()
        ->and(TenantStatus::Canceled->canTransitionTo(TenantStatus::Archived))->toBeTrue();
});

test('canTransitionTo returns false for invalid transition', function () {
    expect(TenantStatus::Prospect->canTransitionTo(TenantStatus::Active))->toBeFalse()
        ->and(TenantStatus::Active->canTransitionTo(TenantStatus::Archived))->toBeFalse()
        ->and(TenantStatus::Archived->canTransitionTo(TenantStatus::Active))->toBeFalse()
        ->and(TenantStatus::Provisioning->canTransitionTo(TenantStatus::Canceled))->toBeFalse();
});

test('canTransitionTo returns false for same status', function () {
    foreach (TenantStatus::cases() as $status) {
        expect($status->canTransitionTo($status))->toBeFalse(
            "Expected {$status->value} cannot transition to itself",
        );
    }
});

// --- isOperational ---

test('Active, Trial, and PastDue are operational', function () {
    expect(TenantStatus::Active->isOperational())->toBeTrue()
        ->and(TenantStatus::Trial->isOperational())->toBeTrue()
        ->and(TenantStatus::PastDue->isOperational())->toBeTrue();
});

test('non-operational statuses', function () {
    expect(TenantStatus::Prospect->isOperational())->toBeFalse()
        ->and(TenantStatus::Provisioning->isOperational())->toBeFalse()
        ->and(TenantStatus::Suspended->isOperational())->toBeFalse()
        ->and(TenantStatus::Canceled->isOperational())->toBeFalse()
        ->and(TenantStatus::Archived->isOperational())->toBeFalse();
});
