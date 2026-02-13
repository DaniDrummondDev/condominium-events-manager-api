<?php

declare(strict_types=1);

use Domain\Communication\Enums\SupportRequestPriority;

test('has correct cases', function () {
    expect(SupportRequestPriority::cases())->toHaveCount(3);
    expect(SupportRequestPriority::Low->value)->toBe('low');
    expect(SupportRequestPriority::Normal->value)->toBe('normal');
    expect(SupportRequestPriority::High->value)->toBe('high');
});

test('labels are correct', function () {
    expect(SupportRequestPriority::Low->label())->toBe('Baixa');
    expect(SupportRequestPriority::Normal->label())->toBe('Normal');
    expect(SupportRequestPriority::High->label())->toBe('Alta');
});
