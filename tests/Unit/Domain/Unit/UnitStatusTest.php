<?php

declare(strict_types=1);

use Domain\Unit\Enums\UnitStatus;

// --- Enum cases ---

test('UnitStatus has Active and Inactive values', function () {
    expect(UnitStatus::Active->value)->toBe('active')
        ->and(UnitStatus::Inactive->value)->toBe('inactive');
});

test('UnitStatus has exactly 2 cases', function () {
    expect(UnitStatus::cases())->toHaveCount(2);
});

// --- isActive ---

test('Active isActive returns true', function () {
    expect(UnitStatus::Active->isActive())->toBeTrue();
});

test('Inactive isActive returns false', function () {
    expect(UnitStatus::Inactive->isActive())->toBeFalse();
});
