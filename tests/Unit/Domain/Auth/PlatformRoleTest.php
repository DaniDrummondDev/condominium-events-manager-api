<?php

declare(strict_types=1);

use Domain\Auth\Enums\PlatformRole;

it('has correct enum values', function () {
    expect(PlatformRole::PlatformOwner->value)->toBe('platform_owner')
        ->and(PlatformRole::PlatformAdmin->value)->toBe('platform_admin')
        ->and(PlatformRole::PlatformSupport->value)->toBe('platform_support');
});

it('requires MFA for owner and admin', function () {
    expect(PlatformRole::PlatformOwner->requiresMfa())->toBeTrue()
        ->and(PlatformRole::PlatformAdmin->requiresMfa())->toBeTrue()
        ->and(PlatformRole::PlatformSupport->requiresMfa())->toBeFalse();
});
