<?php

declare(strict_types=1);

/**
 * Testes de convencao de nomenclatura.
 *
 * Garantem que as classes seguem os padroes definidos:
 * - Eloquent Models terminam com 'Model'
 * - Repositories terminam com 'Repository'
 * - Use Cases possuem metodo execute()
 * - Domain Events sao nomeados no passado
 * - Jobs terminam com 'Job'
 */
arch('Platform Eloquent models must end with Model')
    ->expect('App\Infrastructure\Persistence\Platform\Models')
    ->toHaveSuffix('Model');

arch('Tenant Eloquent models must end with Model')
    ->expect('App\Infrastructure\Persistence\Tenant\Models')
    ->toHaveSuffix('Model');

arch('Platform repository implementations must end with Repository')
    ->expect('App\Infrastructure\Persistence\Platform\Repositories')
    ->toHaveSuffix('Repository');

arch('Tenant repository implementations must end with Repository')
    ->expect('App\Infrastructure\Persistence\Tenant\Repositories')
    ->toHaveSuffix('Repository');

arch('Tenant Use Cases must have an execute method')
    ->expect('Application\Tenant\UseCases')
    ->toHaveMethod('execute');

arch('Unit Use Cases must have an execute method')
    ->expect('Application\Unit\UseCases')
    ->toHaveMethod('execute');

arch('Space Use Cases must have an execute method')
    ->expect('Application\Space\UseCases')
    ->toHaveMethod('execute');

arch('Reservation Use Cases must have an execute method')
    ->expect('Application\Reservation\UseCases')
    ->toHaveMethod('execute');

arch('Billing Use Cases must have an execute method')
    ->expect('Application\Billing\UseCases')
    ->toHaveMethod('execute');

arch('Governance Use Cases must have an execute method')
    ->expect('Application\Governance\UseCases')
    ->toHaveMethod('execute');

arch('People Use Cases must have an execute method')
    ->expect('Application\People\UseCases')
    ->toHaveMethod('execute');

arch('Communication Use Cases must have an execute method')
    ->expect('Application\Communication\UseCases')
    ->toHaveMethod('execute');

arch('Jobs must end with Job')
    ->expect('App\Infrastructure\Jobs')
    ->toHaveSuffix('Job');
