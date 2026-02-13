<?php

declare(strict_types=1);

use Domain\People\Enums\GuestStatus;

// --- Enum cases ---

test('GuestStatus has exactly 5 cases', function () {
    expect(GuestStatus::cases())->toHaveCount(5);
});

test('GuestStatus has correct enum values', function () {
    expect(GuestStatus::Registered->value)->toBe('registered')
        ->and(GuestStatus::CheckedIn->value)->toBe('checked_in')
        ->and(GuestStatus::CheckedOut->value)->toBe('checked_out')
        ->and(GuestStatus::Denied->value)->toBe('denied')
        ->and(GuestStatus::NoShow->value)->toBe('no_show');
});

// --- canTransitionTo ---

test('Registered can transition to CheckedIn, Denied, NoShow', function () {
    $status = GuestStatus::Registered;

    expect($status->canTransitionTo(GuestStatus::CheckedIn))->toBeTrue()
        ->and($status->canTransitionTo(GuestStatus::Denied))->toBeTrue()
        ->and($status->canTransitionTo(GuestStatus::NoShow))->toBeTrue()
        ->and($status->canTransitionTo(GuestStatus::CheckedOut))->toBeFalse();
});

test('CheckedIn can transition to CheckedOut only', function () {
    $status = GuestStatus::CheckedIn;

    expect($status->canTransitionTo(GuestStatus::CheckedOut))->toBeTrue()
        ->and($status->canTransitionTo(GuestStatus::Registered))->toBeFalse()
        ->and($status->canTransitionTo(GuestStatus::Denied))->toBeFalse()
        ->and($status->canTransitionTo(GuestStatus::NoShow))->toBeFalse();
});

test('Terminal states have no transitions', function (GuestStatus $status) {
    foreach (GuestStatus::cases() as $target) {
        expect($status->canTransitionTo($target))->toBeFalse();
    }
})->with([
    GuestStatus::CheckedOut,
    GuestStatus::Denied,
    GuestStatus::NoShow,
]);

// --- allowedTransitions ---

test('Registered has 3 allowed transitions', function () {
    expect(GuestStatus::Registered->allowedTransitions())->toHaveCount(3);
});

test('CheckedIn has 1 allowed transition', function () {
    expect(GuestStatus::CheckedIn->allowedTransitions())->toHaveCount(1);
});

test('Terminal states have 0 allowed transitions', function (GuestStatus $status) {
    expect($status->allowedTransitions())->toHaveCount(0);
})->with([
    GuestStatus::CheckedOut,
    GuestStatus::Denied,
    GuestStatus::NoShow,
]);

// --- isTerminal ---

test('Terminal states return isTerminal true', function (GuestStatus $status) {
    expect($status->isTerminal())->toBeTrue();
})->with([
    GuestStatus::CheckedOut,
    GuestStatus::Denied,
    GuestStatus::NoShow,
]);

test('Non-terminal states return isTerminal false', function (GuestStatus $status) {
    expect($status->isTerminal())->toBeFalse();
})->with([
    GuestStatus::Registered,
    GuestStatus::CheckedIn,
]);

// --- isPresent ---

test('CheckedIn returns isPresent true', function () {
    expect(GuestStatus::CheckedIn->isPresent())->toBeTrue();
});

test('Non-present states return isPresent false', function (GuestStatus $status) {
    expect($status->isPresent())->toBeFalse();
})->with([
    GuestStatus::Registered,
    GuestStatus::CheckedOut,
    GuestStatus::Denied,
    GuestStatus::NoShow,
]);

// --- label ---

test('Each status has a Portuguese label', function () {
    expect(GuestStatus::Registered->label())->toBe('Registrado')
        ->and(GuestStatus::CheckedIn->label())->toBe('Presente')
        ->and(GuestStatus::CheckedOut->label())->toBe('Saiu')
        ->and(GuestStatus::Denied->label())->toBe('Acesso Negado')
        ->and(GuestStatus::NoShow->label())->toBe('Não Compareceu');
});
