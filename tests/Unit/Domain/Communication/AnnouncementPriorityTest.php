<?php

declare(strict_types=1);

use Domain\Communication\Enums\AnnouncementPriority;

test('has correct cases', function () {
    expect(AnnouncementPriority::cases())->toHaveCount(4);
    expect(AnnouncementPriority::Low->value)->toBe('low');
    expect(AnnouncementPriority::Normal->value)->toBe('normal');
    expect(AnnouncementPriority::High->value)->toBe('high');
    expect(AnnouncementPriority::Urgent->value)->toBe('urgent');
});

test('labels are correct', function () {
    expect(AnnouncementPriority::Low->label())->toBe('Baixa');
    expect(AnnouncementPriority::Normal->label())->toBe('Normal');
    expect(AnnouncementPriority::High->label())->toBe('Alta');
    expect(AnnouncementPriority::Urgent->label())->toBe('Urgente');
});
