<?php

declare(strict_types=1);

use Domain\People\Enums\ServiceProviderStatus;

// --- Enum cases ---

test('ServiceProviderStatus has exactly 3 cases', function () {
    expect(ServiceProviderStatus::cases())->toHaveCount(3);
});

test('ServiceProviderStatus has correct enum values', function () {
    expect(ServiceProviderStatus::Active->value)->toBe('active')
        ->and(ServiceProviderStatus::Inactive->value)->toBe('inactive')
        ->and(ServiceProviderStatus::Blocked->value)->toBe('blocked');
});

// --- isActive ---

test('Active returns isActive true', function () {
    expect(ServiceProviderStatus::Active->isActive())->toBeTrue();
});

test('Non-active states return isActive false', function (ServiceProviderStatus $status) {
    expect($status->isActive())->toBeFalse();
})->with([
    ServiceProviderStatus::Inactive,
    ServiceProviderStatus::Blocked,
]);

// --- canBeLinkedToVisits ---

test('Active returns canBeLinkedToVisits true', function () {
    expect(ServiceProviderStatus::Active->canBeLinkedToVisits())->toBeTrue();
});

test('Non-active states return canBeLinkedToVisits false', function (ServiceProviderStatus $status) {
    expect($status->canBeLinkedToVisits())->toBeFalse();
})->with([
    ServiceProviderStatus::Inactive,
    ServiceProviderStatus::Blocked,
]);

// --- label ---

test('Each status has a Portuguese label', function () {
    expect(ServiceProviderStatus::Active->label())->toBe('Ativo')
        ->and(ServiceProviderStatus::Inactive->label())->toBe('Inativo')
        ->and(ServiceProviderStatus::Blocked->label())->toBe('Bloqueado');
});
