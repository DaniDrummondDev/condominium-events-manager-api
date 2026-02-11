<?php

declare(strict_types=1);

use Domain\Space\Enums\SpaceStatus;

// --- Enum cases ---

test('SpaceStatus has exactly 3 cases', function () {
    expect(SpaceStatus::cases())->toHaveCount(3);
});

test('SpaceStatus has correct enum values', function () {
    expect(SpaceStatus::Active->value)->toBe('active')
        ->and(SpaceStatus::Inactive->value)->toBe('inactive')
        ->and(SpaceStatus::Maintenance->value)->toBe('maintenance');
});

// --- isActive ---

test('Active isActive returns true', function () {
    expect(SpaceStatus::Active->isActive())->toBeTrue();
});

test('Inactive isActive returns false', function () {
    expect(SpaceStatus::Inactive->isActive())->toBeFalse();
});

test('Maintenance isActive returns false', function () {
    expect(SpaceStatus::Maintenance->isActive())->toBeFalse();
});

// --- canAcceptReservations ---

test('Active canAcceptReservations returns true', function () {
    expect(SpaceStatus::Active->canAcceptReservations())->toBeTrue();
});

test('Inactive canAcceptReservations returns false', function () {
    expect(SpaceStatus::Inactive->canAcceptReservations())->toBeFalse();
});

test('Maintenance canAcceptReservations returns false', function () {
    expect(SpaceStatus::Maintenance->canAcceptReservations())->toBeFalse();
});
