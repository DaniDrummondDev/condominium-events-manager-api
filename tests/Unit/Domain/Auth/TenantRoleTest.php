<?php

declare(strict_types=1);

use Domain\Auth\Enums\TenantRole;

it('has correct enum values', function () {
    expect(TenantRole::Sindico->value)->toBe('sindico')
        ->and(TenantRole::Administradora->value)->toBe('administradora')
        ->and(TenantRole::Condomino->value)->toBe('condomino')
        ->and(TenantRole::Funcionario->value)->toBe('funcionario');
});

it('requires MFA for sindico and administradora', function () {
    expect(TenantRole::Sindico->requiresMfa())->toBeTrue()
        ->and(TenantRole::Administradora->requiresMfa())->toBeTrue()
        ->and(TenantRole::Condomino->requiresMfa())->toBeFalse()
        ->and(TenantRole::Funcionario->requiresMfa())->toBeFalse();
});
