<?php

declare(strict_types=1);

use Domain\People\Enums\ServiceProviderVisitStatus;

// --- Enum cases ---

test('ServiceProviderVisitStatus has exactly 5 cases', function () {
    expect(ServiceProviderVisitStatus::cases())->toHaveCount(5);
});

test('ServiceProviderVisitStatus has correct enum values', function () {
    expect(ServiceProviderVisitStatus::Scheduled->value)->toBe('scheduled')
        ->and(ServiceProviderVisitStatus::CheckedIn->value)->toBe('checked_in')
        ->and(ServiceProviderVisitStatus::CheckedOut->value)->toBe('checked_out')
        ->and(ServiceProviderVisitStatus::Canceled->value)->toBe('canceled')
        ->and(ServiceProviderVisitStatus::NoShow->value)->toBe('no_show');
});

// --- canTransitionTo ---

test('Scheduled can transition to CheckedIn, Canceled, NoShow', function () {
    $status = ServiceProviderVisitStatus::Scheduled;

    expect($status->canTransitionTo(ServiceProviderVisitStatus::CheckedIn))->toBeTrue()
        ->and($status->canTransitionTo(ServiceProviderVisitStatus::Canceled))->toBeTrue()
        ->and($status->canTransitionTo(ServiceProviderVisitStatus::NoShow))->toBeTrue()
        ->and($status->canTransitionTo(ServiceProviderVisitStatus::CheckedOut))->toBeFalse();
});

test('CheckedIn can transition to CheckedOut only', function () {
    $status = ServiceProviderVisitStatus::CheckedIn;

    expect($status->canTransitionTo(ServiceProviderVisitStatus::CheckedOut))->toBeTrue()
        ->and($status->canTransitionTo(ServiceProviderVisitStatus::Scheduled))->toBeFalse()
        ->and($status->canTransitionTo(ServiceProviderVisitStatus::Canceled))->toBeFalse()
        ->and($status->canTransitionTo(ServiceProviderVisitStatus::NoShow))->toBeFalse();
});

test('Terminal states have no transitions', function (ServiceProviderVisitStatus $status) {
    foreach (ServiceProviderVisitStatus::cases() as $target) {
        expect($status->canTransitionTo($target))->toBeFalse();
    }
})->with([
    ServiceProviderVisitStatus::CheckedOut,
    ServiceProviderVisitStatus::Canceled,
    ServiceProviderVisitStatus::NoShow,
]);

// --- allowedTransitions ---

test('Scheduled has 3 allowed transitions', function () {
    expect(ServiceProviderVisitStatus::Scheduled->allowedTransitions())->toHaveCount(3);
});

test('CheckedIn has 1 allowed transition', function () {
    expect(ServiceProviderVisitStatus::CheckedIn->allowedTransitions())->toHaveCount(1);
});

test('Terminal states have 0 allowed transitions', function (ServiceProviderVisitStatus $status) {
    expect($status->allowedTransitions())->toHaveCount(0);
})->with([
    ServiceProviderVisitStatus::CheckedOut,
    ServiceProviderVisitStatus::Canceled,
    ServiceProviderVisitStatus::NoShow,
]);

// --- isTerminal ---

test('Terminal states return isTerminal true', function (ServiceProviderVisitStatus $status) {
    expect($status->isTerminal())->toBeTrue();
})->with([
    ServiceProviderVisitStatus::CheckedOut,
    ServiceProviderVisitStatus::Canceled,
    ServiceProviderVisitStatus::NoShow,
]);

test('Non-terminal states return isTerminal false', function (ServiceProviderVisitStatus $status) {
    expect($status->isTerminal())->toBeFalse();
})->with([
    ServiceProviderVisitStatus::Scheduled,
    ServiceProviderVisitStatus::CheckedIn,
]);

// --- isPresent ---

test('CheckedIn returns isPresent true', function () {
    expect(ServiceProviderVisitStatus::CheckedIn->isPresent())->toBeTrue();
});

test('Non-present states return isPresent false', function (ServiceProviderVisitStatus $status) {
    expect($status->isPresent())->toBeFalse();
})->with([
    ServiceProviderVisitStatus::Scheduled,
    ServiceProviderVisitStatus::CheckedOut,
    ServiceProviderVisitStatus::Canceled,
    ServiceProviderVisitStatus::NoShow,
]);

// --- label ---

test('Each status has a Portuguese label', function () {
    expect(ServiceProviderVisitStatus::Scheduled->label())->toBe('Agendado')
        ->and(ServiceProviderVisitStatus::CheckedIn->label())->toBe('Presente')
        ->and(ServiceProviderVisitStatus::CheckedOut->label())->toBe('Saiu')
        ->and(ServiceProviderVisitStatus::Canceled->label())->toBe('Cancelado')
        ->and(ServiceProviderVisitStatus::NoShow->label())->toBe('Não Compareceu');
});
