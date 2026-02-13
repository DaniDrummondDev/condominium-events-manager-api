<?php

declare(strict_types=1);

use Domain\Communication\Enums\AudienceType;

test('has correct cases', function () {
    expect(AudienceType::cases())->toHaveCount(3);
    expect(AudienceType::All->value)->toBe('all');
    expect(AudienceType::Block->value)->toBe('block');
    expect(AudienceType::Units->value)->toBe('units');
});

test('block and units require ids', function () {
    expect(AudienceType::Block->requiresIds())->toBeTrue();
    expect(AudienceType::Units->requiresIds())->toBeTrue();
});

test('all does not require ids', function () {
    expect(AudienceType::All->requiresIds())->toBeFalse();
});

test('labels are correct', function () {
    expect(AudienceType::All->label())->toBe('Todos');
    expect(AudienceType::Block->label())->toBe('Bloco');
    expect(AudienceType::Units->label())->toBe('Unidades');
});
