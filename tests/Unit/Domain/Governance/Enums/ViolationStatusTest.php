<?php

declare(strict_types=1);

use Domain\Governance\Enums\ViolationStatus;

// --- Enum cases ---

test('ViolationStatus has exactly 4 cases', function () {
    expect(ViolationStatus::cases())->toHaveCount(4);
});

test('ViolationStatus has correct enum values', function () {
    expect(ViolationStatus::Open->value)->toBe('open')
        ->and(ViolationStatus::Contested->value)->toBe('contested')
        ->and(ViolationStatus::Upheld->value)->toBe('upheld')
        ->and(ViolationStatus::Revoked->value)->toBe('revoked');
});

// --- canTransitionTo ---

test('Open can transition to Upheld, Revoked, Contested', function () {
    $status = ViolationStatus::Open;

    expect($status->canTransitionTo(ViolationStatus::Upheld))->toBeTrue()
        ->and($status->canTransitionTo(ViolationStatus::Revoked))->toBeTrue()
        ->and($status->canTransitionTo(ViolationStatus::Contested))->toBeTrue();
});

test('Open cannot transition to Open', function () {
    expect(ViolationStatus::Open->canTransitionTo(ViolationStatus::Open))->toBeFalse();
});

test('Contested can transition to Upheld, Revoked', function () {
    $status = ViolationStatus::Contested;

    expect($status->canTransitionTo(ViolationStatus::Upheld))->toBeTrue()
        ->and($status->canTransitionTo(ViolationStatus::Revoked))->toBeTrue();
});

test('Contested cannot transition to Open or Contested', function () {
    $status = ViolationStatus::Contested;

    expect($status->canTransitionTo(ViolationStatus::Open))->toBeFalse()
        ->and($status->canTransitionTo(ViolationStatus::Contested))->toBeFalse();
});

test('Terminal states have no transitions', function (ViolationStatus $status) {
    foreach (ViolationStatus::cases() as $target) {
        expect($status->canTransitionTo($target))->toBeFalse();
    }
})->with([
    ViolationStatus::Upheld,
    ViolationStatus::Revoked,
]);

// --- isTerminal ---

test('Terminal states return isTerminal true', function (ViolationStatus $status) {
    expect($status->isTerminal())->toBeTrue();
})->with([
    ViolationStatus::Upheld,
    ViolationStatus::Revoked,
]);

test('Non-terminal states return isTerminal false', function (ViolationStatus $status) {
    expect($status->isTerminal())->toBeFalse();
})->with([
    ViolationStatus::Open,
    ViolationStatus::Contested,
]);

// --- isActive ---

test('Active states return isActive true', function (ViolationStatus $status) {
    expect($status->isActive())->toBeTrue();
})->with([
    ViolationStatus::Open,
    ViolationStatus::Contested,
]);

test('Non-active states return isActive false', function (ViolationStatus $status) {
    expect($status->isActive())->toBeFalse();
})->with([
    ViolationStatus::Upheld,
    ViolationStatus::Revoked,
]);
