<?php

declare(strict_types=1);

use Domain\Governance\Enums\ContestationStatus;

// --- Enum cases ---

test('ContestationStatus has exactly 3 cases', function () {
    expect(ContestationStatus::cases())->toHaveCount(3);
});

test('ContestationStatus has correct enum values', function () {
    expect(ContestationStatus::Pending->value)->toBe('pending')
        ->and(ContestationStatus::Accepted->value)->toBe('accepted')
        ->and(ContestationStatus::Rejected->value)->toBe('rejected');
});

// --- isTerminal ---

test('Terminal states return isTerminal true', function (ContestationStatus $status) {
    expect($status->isTerminal())->toBeTrue();
})->with([
    ContestationStatus::Accepted,
    ContestationStatus::Rejected,
]);

test('Pending returns isTerminal false', function () {
    expect(ContestationStatus::Pending->isTerminal())->toBeFalse();
});
