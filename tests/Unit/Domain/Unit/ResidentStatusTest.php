<?php

declare(strict_types=1);

use Domain\Unit\Enums\ResidentStatus;

// --- Enum cases ---

test('ResidentStatus has exactly 3 cases', function () {
    expect(ResidentStatus::cases())->toHaveCount(3);
});

test('ResidentStatus has correct enum values', function () {
    expect(ResidentStatus::Active->value)->toBe('active')
        ->and(ResidentStatus::Inactive->value)->toBe('inactive')
        ->and(ResidentStatus::Invited->value)->toBe('invited');
});

// --- isActive ---

test('Active isActive returns true', function () {
    expect(ResidentStatus::Active->isActive())->toBeTrue();
});

test('Inactive isActive returns false', function () {
    expect(ResidentStatus::Inactive->isActive())->toBeFalse();
});

test('Invited isActive returns false', function () {
    expect(ResidentStatus::Invited->isActive())->toBeFalse();
});

// --- canLogin ---

test('Active canLogin returns true', function () {
    expect(ResidentStatus::Active->canLogin())->toBeTrue();
});

test('Inactive canLogin returns false', function () {
    expect(ResidentStatus::Inactive->canLogin())->toBeFalse();
});

test('Invited canLogin returns false', function () {
    expect(ResidentStatus::Invited->canLogin())->toBeFalse();
});
