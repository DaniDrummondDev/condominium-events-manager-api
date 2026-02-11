<?php

declare(strict_types=1);

use Domain\Unit\Enums\BlockStatus;

// --- Enum cases ---

test('BlockStatus has Active and Inactive values', function () {
    expect(BlockStatus::Active->value)->toBe('active')
        ->and(BlockStatus::Inactive->value)->toBe('inactive');
});

test('BlockStatus has exactly 2 cases', function () {
    expect(BlockStatus::cases())->toHaveCount(2);
});

// --- isActive ---

test('Active isActive returns true', function () {
    expect(BlockStatus::Active->isActive())->toBeTrue();
});

test('Inactive isActive returns false', function () {
    expect(BlockStatus::Inactive->isActive())->toBeFalse();
});
