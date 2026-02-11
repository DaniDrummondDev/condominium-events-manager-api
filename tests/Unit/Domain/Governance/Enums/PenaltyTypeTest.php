<?php

declare(strict_types=1);

use Domain\Governance\Enums\PenaltyType;

// --- Enum cases ---

test('PenaltyType has exactly 3 cases', function () {
    expect(PenaltyType::cases())->toHaveCount(3);
});

test('PenaltyType has correct enum values', function () {
    expect(PenaltyType::Warning->value)->toBe('warning')
        ->and(PenaltyType::TemporaryBlock->value)->toBe('temporary_block')
        ->and(PenaltyType::PermanentBlock->value)->toBe('permanent_block');
});

// --- label ---

test('Each penalty type has a Portuguese label', function () {
    expect(PenaltyType::Warning->label())->toBe('Advertência')
        ->and(PenaltyType::TemporaryBlock->label())->toBe('Bloqueio Temporário')
        ->and(PenaltyType::PermanentBlock->label())->toBe('Bloqueio Permanente');
});

// --- isBlocking ---

test('Blocking penalty types return isBlocking true', function (PenaltyType $type) {
    expect($type->isBlocking())->toBeTrue();
})->with([
    PenaltyType::TemporaryBlock,
    PenaltyType::PermanentBlock,
]);

test('Warning returns isBlocking false', function () {
    expect(PenaltyType::Warning->isBlocking())->toBeFalse();
});
