<?php

declare(strict_types=1);

use Domain\Governance\Enums\ViolationSeverity;

// --- Enum cases ---

test('ViolationSeverity has exactly 4 cases', function () {
    expect(ViolationSeverity::cases())->toHaveCount(4);
});

test('ViolationSeverity has correct enum values', function () {
    expect(ViolationSeverity::Low->value)->toBe('low')
        ->and(ViolationSeverity::Medium->value)->toBe('medium')
        ->and(ViolationSeverity::High->value)->toBe('high')
        ->and(ViolationSeverity::Critical->value)->toBe('critical');
});

// --- label ---

test('Each violation severity has a Portuguese label', function () {
    expect(ViolationSeverity::Low->label())->toBe('Baixa')
        ->and(ViolationSeverity::Medium->label())->toBe('Média')
        ->and(ViolationSeverity::High->label())->toBe('Alta')
        ->and(ViolationSeverity::Critical->label())->toBe('Crítica');
});

// --- isEscalatable ---

test('Escalatable severities return isEscalatable true', function (ViolationSeverity $severity) {
    expect($severity->isEscalatable())->toBeTrue();
})->with([
    ViolationSeverity::Low,
    ViolationSeverity::Medium,
    ViolationSeverity::High,
]);

test('Critical returns isEscalatable false', function () {
    expect(ViolationSeverity::Critical->isEscalatable())->toBeFalse();
});
