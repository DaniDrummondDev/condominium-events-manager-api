<?php

declare(strict_types=1);

use Domain\Governance\Enums\PenaltyStatus;

// --- Enum cases ---

test('PenaltyStatus has exactly 3 cases', function () {
    expect(PenaltyStatus::cases())->toHaveCount(3);
});

test('PenaltyStatus has correct enum values', function () {
    expect(PenaltyStatus::Active->value)->toBe('active')
        ->and(PenaltyStatus::Expired->value)->toBe('expired')
        ->and(PenaltyStatus::Revoked->value)->toBe('revoked');
});

// --- isActive ---

test('Active returns isActive true', function () {
    expect(PenaltyStatus::Active->isActive())->toBeTrue();
});

test('Non-active states return isActive false', function (PenaltyStatus $status) {
    expect($status->isActive())->toBeFalse();
})->with([
    PenaltyStatus::Expired,
    PenaltyStatus::Revoked,
]);

// --- isTerminal ---

test('Terminal states return isTerminal true', function (PenaltyStatus $status) {
    expect($status->isTerminal())->toBeTrue();
})->with([
    PenaltyStatus::Expired,
    PenaltyStatus::Revoked,
]);

test('Active returns isTerminal false', function () {
    expect(PenaltyStatus::Active->isTerminal())->toBeFalse();
});
