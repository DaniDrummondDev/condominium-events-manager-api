<?php

declare(strict_types=1);

use Domain\Communication\Enums\SupportRequestCategory;

test('has correct cases', function () {
    expect(SupportRequestCategory::cases())->toHaveCount(5);
    expect(SupportRequestCategory::Maintenance->value)->toBe('maintenance');
    expect(SupportRequestCategory::Noise->value)->toBe('noise');
    expect(SupportRequestCategory::Security->value)->toBe('security');
    expect(SupportRequestCategory::General->value)->toBe('general');
    expect(SupportRequestCategory::Other->value)->toBe('other');
});

test('labels are correct', function () {
    expect(SupportRequestCategory::Maintenance->label())->toBe('Manutenção');
    expect(SupportRequestCategory::Noise->label())->toBe('Barulho');
    expect(SupportRequestCategory::Security->label())->toBe('Segurança');
    expect(SupportRequestCategory::General->label())->toBe('Geral');
    expect(SupportRequestCategory::Other->label())->toBe('Outro');
});
