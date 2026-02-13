<?php

declare(strict_types=1);

use Domain\People\Enums\ServiceType;

// --- Enum cases ---

test('ServiceType has exactly 8 cases', function () {
    expect(ServiceType::cases())->toHaveCount(8);
});

test('ServiceType has correct enum values', function () {
    expect(ServiceType::Buffet->value)->toBe('buffet')
        ->and(ServiceType::Cleaning->value)->toBe('cleaning')
        ->and(ServiceType::Decoration->value)->toBe('decoration')
        ->and(ServiceType::Dj->value)->toBe('dj')
        ->and(ServiceType::Security->value)->toBe('security')
        ->and(ServiceType::Maintenance->value)->toBe('maintenance')
        ->and(ServiceType::Moving->value)->toBe('moving')
        ->and(ServiceType::Other->value)->toBe('other');
});

// --- label ---

test('Each service type has a Portuguese label', function () {
    expect(ServiceType::Buffet->label())->toBe('Buffet')
        ->and(ServiceType::Cleaning->label())->toBe('Limpeza')
        ->and(ServiceType::Decoration->label())->toBe('Decoração')
        ->and(ServiceType::Dj->label())->toBe('DJ')
        ->and(ServiceType::Security->label())->toBe('Segurança')
        ->and(ServiceType::Maintenance->label())->toBe('Manutenção')
        ->and(ServiceType::Moving->label())->toBe('Mudança')
        ->and(ServiceType::Other->label())->toBe('Outro');
});
