<?php

declare(strict_types=1);

use Domain\Unit\Enums\ResidentRole;

// --- Enum cases ---

test('ResidentRole has exactly 4 cases', function () {
    expect(ResidentRole::cases())->toHaveCount(4);
});

test('ResidentRole has correct enum values', function () {
    expect(ResidentRole::Owner->value)->toBe('owner')
        ->and(ResidentRole::TenantResident->value)->toBe('tenant_resident')
        ->and(ResidentRole::Dependent->value)->toBe('dependent')
        ->and(ResidentRole::Authorized->value)->toBe('authorized');
});

// --- Labels ---

test('Owner label is Proprietario', function () {
    expect(ResidentRole::Owner->label())->toBe('Proprietário');
});

test('TenantResident label is Inquilino', function () {
    expect(ResidentRole::TenantResident->label())->toBe('Inquilino');
});

test('Dependent label is Dependente', function () {
    expect(ResidentRole::Dependent->label())->toBe('Dependente');
});

test('Authorized label is Autorizado', function () {
    expect(ResidentRole::Authorized->label())->toBe('Autorizado');
});

// --- canManageUnit ---

test('Owner can manage unit', function () {
    expect(ResidentRole::Owner->canManageUnit())->toBeTrue();
});

test('TenantResident can manage unit', function () {
    expect(ResidentRole::TenantResident->canManageUnit())->toBeTrue();
});

test('Dependent cannot manage unit', function () {
    expect(ResidentRole::Dependent->canManageUnit())->toBeFalse();
});

test('Authorized cannot manage unit', function () {
    expect(ResidentRole::Authorized->canManageUnit())->toBeFalse();
});
