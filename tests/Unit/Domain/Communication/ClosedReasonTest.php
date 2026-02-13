<?php

declare(strict_types=1);

use Domain\Communication\Enums\ClosedReason;

test('has correct cases', function () {
    expect(ClosedReason::cases())->toHaveCount(3);
    expect(ClosedReason::Resolved->value)->toBe('resolved');
    expect(ClosedReason::AutoClosed->value)->toBe('auto_closed');
    expect(ClosedReason::AdminClosed->value)->toBe('admin_closed');
});

test('labels are correct', function () {
    expect(ClosedReason::Resolved->label())->toBe('Resolvida');
    expect(ClosedReason::AutoClosed->label())->toBe('Fechada Automaticamente');
    expect(ClosedReason::AdminClosed->label())->toBe('Fechada pelo Administrador');
});
