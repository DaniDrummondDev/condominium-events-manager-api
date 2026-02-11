<?php

declare(strict_types=1);

/**
 * Testes de dependencia entre camadas (Clean Architecture).
 *
 * Regras:
 * - Domain\       → NÃO depende de nada externo (sem Illuminate\, App\, Application\)
 * - Application\  → Pode usar Domain\. NÃO pode usar App\, Illuminate\
 * - App\Interface\ → Pode usar Application\. NÃO usa Domain\ diretamente
 * - App\Infrastructure\ → Pode usar Domain\ e Application\
 */
arch('Domain layer must not depend on Laravel')
    ->expect('Domain')
    ->toUseNothing()
    ->ignoring('Domain')
    ->ignoring('Symfony\Component\Uid')
    ->ignoring('DateTimeImmutable')
    ->ignoring('Stringable')
    ->ignoring('RuntimeException')
    ->ignoring('Throwable');

arch('Domain layer must not use Application namespace')
    ->expect('Domain')
    ->not->toBeUsedIn('Domain')
    ->not->toUse('Application');

arch('Domain layer must not use App namespace')
    ->expect('Domain')
    ->not->toUse('App');

arch('Domain layer must not use Illuminate namespace')
    ->expect('Domain')
    ->not->toUse('Illuminate');

arch('Application layer may use Domain')
    ->expect('Application')
    ->toOnlyUse([
        'Domain',
        'Application',
        'DateTimeImmutable',
        'Stringable',
    ]);

arch('Application layer must not use App namespace')
    ->expect('Application')
    ->not->toUse('App');

arch('Application layer must not use Illuminate namespace')
    ->expect('Application')
    ->not->toUse('Illuminate');
