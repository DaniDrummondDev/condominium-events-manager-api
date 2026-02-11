<?php

declare(strict_types=1);

use Domain\Reservation\Enums\ReservationStatus;

// --- Enum cases ---

test('ReservationStatus has exactly 7 cases', function () {
    expect(ReservationStatus::cases())->toHaveCount(7);
});

test('ReservationStatus has correct enum values', function () {
    expect(ReservationStatus::PendingApproval->value)->toBe('pending_approval')
        ->and(ReservationStatus::Confirmed->value)->toBe('confirmed')
        ->and(ReservationStatus::Rejected->value)->toBe('rejected')
        ->and(ReservationStatus::Canceled->value)->toBe('canceled')
        ->and(ReservationStatus::InProgress->value)->toBe('in_progress')
        ->and(ReservationStatus::Completed->value)->toBe('completed')
        ->and(ReservationStatus::NoShow->value)->toBe('no_show');
});

// --- canTransitionTo ---

test('PendingApproval can transition to Confirmed, Rejected, Canceled', function () {
    $status = ReservationStatus::PendingApproval;

    expect($status->canTransitionTo(ReservationStatus::Confirmed))->toBeTrue()
        ->and($status->canTransitionTo(ReservationStatus::Rejected))->toBeTrue()
        ->and($status->canTransitionTo(ReservationStatus::Canceled))->toBeTrue()
        ->and($status->canTransitionTo(ReservationStatus::InProgress))->toBeFalse()
        ->and($status->canTransitionTo(ReservationStatus::Completed))->toBeFalse()
        ->and($status->canTransitionTo(ReservationStatus::NoShow))->toBeFalse();
});

test('Confirmed can transition to Canceled and InProgress', function () {
    $status = ReservationStatus::Confirmed;

    expect($status->canTransitionTo(ReservationStatus::Canceled))->toBeTrue()
        ->and($status->canTransitionTo(ReservationStatus::InProgress))->toBeTrue()
        ->and($status->canTransitionTo(ReservationStatus::PendingApproval))->toBeFalse()
        ->and($status->canTransitionTo(ReservationStatus::Rejected))->toBeFalse()
        ->and($status->canTransitionTo(ReservationStatus::Completed))->toBeFalse()
        ->and($status->canTransitionTo(ReservationStatus::NoShow))->toBeFalse();
});

test('InProgress can transition to Completed and NoShow', function () {
    $status = ReservationStatus::InProgress;

    expect($status->canTransitionTo(ReservationStatus::Completed))->toBeTrue()
        ->and($status->canTransitionTo(ReservationStatus::NoShow))->toBeTrue()
        ->and($status->canTransitionTo(ReservationStatus::PendingApproval))->toBeFalse()
        ->and($status->canTransitionTo(ReservationStatus::Confirmed))->toBeFalse()
        ->and($status->canTransitionTo(ReservationStatus::Rejected))->toBeFalse()
        ->and($status->canTransitionTo(ReservationStatus::Canceled))->toBeFalse();
});

test('Terminal states have no transitions', function (ReservationStatus $status) {
    foreach (ReservationStatus::cases() as $target) {
        expect($status->canTransitionTo($target))->toBeFalse();
    }
})->with([
    ReservationStatus::Rejected,
    ReservationStatus::Canceled,
    ReservationStatus::Completed,
    ReservationStatus::NoShow,
]);

// --- allowedTransitions ---

test('PendingApproval has 3 allowed transitions', function () {
    expect(ReservationStatus::PendingApproval->allowedTransitions())->toHaveCount(3);
});

test('Confirmed has 2 allowed transitions', function () {
    expect(ReservationStatus::Confirmed->allowedTransitions())->toHaveCount(2);
});

test('InProgress has 2 allowed transitions', function () {
    expect(ReservationStatus::InProgress->allowedTransitions())->toHaveCount(2);
});

test('Terminal states have 0 allowed transitions', function (ReservationStatus $status) {
    expect($status->allowedTransitions())->toHaveCount(0);
})->with([
    ReservationStatus::Rejected,
    ReservationStatus::Canceled,
    ReservationStatus::Completed,
    ReservationStatus::NoShow,
]);

// --- isActive ---

test('Active states return isActive true', function (ReservationStatus $status) {
    expect($status->isActive())->toBeTrue();
})->with([
    ReservationStatus::PendingApproval,
    ReservationStatus::Confirmed,
    ReservationStatus::InProgress,
]);

test('Non-active states return isActive false', function (ReservationStatus $status) {
    expect($status->isActive())->toBeFalse();
})->with([
    ReservationStatus::Rejected,
    ReservationStatus::Canceled,
    ReservationStatus::Completed,
    ReservationStatus::NoShow,
]);

// --- isPending ---

test('PendingApproval returns isPending true', function () {
    expect(ReservationStatus::PendingApproval->isPending())->toBeTrue();
});

test('Non-pending states return isPending false', function (ReservationStatus $status) {
    expect($status->isPending())->toBeFalse();
})->with([
    ReservationStatus::Confirmed,
    ReservationStatus::Rejected,
    ReservationStatus::Canceled,
    ReservationStatus::InProgress,
    ReservationStatus::Completed,
    ReservationStatus::NoShow,
]);

// --- isTerminal ---

test('Terminal states return isTerminal true', function (ReservationStatus $status) {
    expect($status->isTerminal())->toBeTrue();
})->with([
    ReservationStatus::Rejected,
    ReservationStatus::Canceled,
    ReservationStatus::Completed,
    ReservationStatus::NoShow,
]);

test('Non-terminal states return isTerminal false', function (ReservationStatus $status) {
    expect($status->isTerminal())->toBeFalse();
})->with([
    ReservationStatus::PendingApproval,
    ReservationStatus::Confirmed,
    ReservationStatus::InProgress,
]);

// --- label ---

test('Each status has a Portuguese label', function () {
    expect(ReservationStatus::PendingApproval->label())->toBe('Aguardando Aprovação')
        ->and(ReservationStatus::Confirmed->label())->toBe('Confirmada')
        ->and(ReservationStatus::Rejected->label())->toBe('Rejeitada')
        ->and(ReservationStatus::Canceled->label())->toBe('Cancelada')
        ->and(ReservationStatus::InProgress->label())->toBe('Em Andamento')
        ->and(ReservationStatus::Completed->label())->toBe('Concluída')
        ->and(ReservationStatus::NoShow->label())->toBe('Não Compareceu');
});
