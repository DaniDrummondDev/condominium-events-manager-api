<?php

declare(strict_types=1);

/**
 * Testes de isolamento de tenant.
 *
 * Garantem que Models de tenant usam conexao 'tenant' e
 * Models de plataforma usam conexao 'platform'.
 *
 * Estes testes serao plenamente funcionais apos a Fase 1
 * quando os Models Eloquent existirem.
 */
arch('Tenant models must be in Tenant namespace')
    ->expect('App\Infrastructure\Persistence\Tenant\Models')
    ->toBeClasses();

arch('Platform models must be in Platform namespace')
    ->expect('App\Infrastructure\Persistence\Platform\Models')
    ->toBeClasses();

arch('Infrastructure classes must not bypass repository pattern')
    ->expect('App\Interface')
    ->not->toUse('Illuminate\Support\Facades\DB');
