<?php

declare(strict_types=1);

use Domain\Unit\Enums\UnitType;

// --- Enum cases ---

test('UnitType has exactly 5 cases', function () {
    expect(UnitType::cases())->toHaveCount(5);
});

test('UnitType has correct enum values', function () {
    expect(UnitType::Apartment->value)->toBe('apartment')
        ->and(UnitType::House->value)->toBe('house')
        ->and(UnitType::Store->value)->toBe('store')
        ->and(UnitType::Office->value)->toBe('office')
        ->and(UnitType::Other->value)->toBe('other');
});

// --- Labels ---

test('Apartment label is Apartamento', function () {
    expect(UnitType::Apartment->label())->toBe('Apartamento');
});

test('House label is Casa', function () {
    expect(UnitType::House->label())->toBe('Casa');
});

test('Store label is Loja', function () {
    expect(UnitType::Store->label())->toBe('Loja');
});

test('Office label is Sala Comercial', function () {
    expect(UnitType::Office->label())->toBe('Sala Comercial');
});

test('Other label is Outro', function () {
    expect(UnitType::Other->label())->toBe('Outro');
});
