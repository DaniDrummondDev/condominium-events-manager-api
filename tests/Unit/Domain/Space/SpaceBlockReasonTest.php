<?php

declare(strict_types=1);

use Domain\Space\Enums\SpaceBlockReason;

// --- Enum cases ---

test('SpaceBlockReason has exactly 4 cases', function () {
    expect(SpaceBlockReason::cases())->toHaveCount(4);
});

test('SpaceBlockReason has correct enum values', function () {
    expect(SpaceBlockReason::Maintenance->value)->toBe('maintenance')
        ->and(SpaceBlockReason::Holiday->value)->toBe('holiday')
        ->and(SpaceBlockReason::Event->value)->toBe('event')
        ->and(SpaceBlockReason::Administrative->value)->toBe('administrative');
});

// --- Labels ---

test('Maintenance label is Manutenção', function () {
    expect(SpaceBlockReason::Maintenance->label())->toBe('Manutenção');
});

test('Holiday label is Feriado', function () {
    expect(SpaceBlockReason::Holiday->label())->toBe('Feriado');
});

test('Event label is Evento', function () {
    expect(SpaceBlockReason::Event->label())->toBe('Evento');
});

test('Administrative label is Administrativo', function () {
    expect(SpaceBlockReason::Administrative->label())->toBe('Administrativo');
});
