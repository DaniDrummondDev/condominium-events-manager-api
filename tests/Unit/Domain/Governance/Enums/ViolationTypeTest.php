<?php

declare(strict_types=1);

use Domain\Governance\Enums\ViolationType;

// --- Enum cases ---

test('ViolationType has exactly 7 cases', function () {
    expect(ViolationType::cases())->toHaveCount(7);
});

test('ViolationType has correct enum values', function () {
    expect(ViolationType::NoShow->value)->toBe('no_show')
        ->and(ViolationType::LateCancellation->value)->toBe('late_cancellation')
        ->and(ViolationType::CapacityExceeded->value)->toBe('capacity_exceeded')
        ->and(ViolationType::NoiseComplaint->value)->toBe('noise_complaint')
        ->and(ViolationType::Damage->value)->toBe('damage')
        ->and(ViolationType::RuleViolation->value)->toBe('rule_violation')
        ->and(ViolationType::Other->value)->toBe('other');
});

// --- label ---

test('Each violation type has a Portuguese label', function () {
    expect(ViolationType::NoShow->label())->toBe('Não Comparecimento')
        ->and(ViolationType::LateCancellation->label())->toBe('Cancelamento Tardio')
        ->and(ViolationType::CapacityExceeded->label())->toBe('Capacidade Excedida')
        ->and(ViolationType::NoiseComplaint->label())->toBe('Reclamação de Barulho')
        ->and(ViolationType::Damage->label())->toBe('Dano')
        ->and(ViolationType::RuleViolation->label())->toBe('Violação de Regra')
        ->and(ViolationType::Other->label())->toBe('Outro');
});

// --- isAutomatic ---

test('NoShow returns isAutomatic true', function () {
    expect(ViolationType::NoShow->isAutomatic())->toBeTrue();
});

test('LateCancellation returns isAutomatic true', function () {
    expect(ViolationType::LateCancellation->isAutomatic())->toBeTrue();
});

test('Manual violation types return isAutomatic false', function (ViolationType $type) {
    expect($type->isAutomatic())->toBeFalse();
})->with([
    ViolationType::CapacityExceeded,
    ViolationType::NoiseComplaint,
    ViolationType::Damage,
    ViolationType::RuleViolation,
    ViolationType::Other,
]);
