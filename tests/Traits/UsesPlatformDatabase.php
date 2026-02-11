<?php

declare(strict_types=1);

namespace Tests\Traits;

/**
 * Trait para testes que precisam acessar o banco Platform.
 *
 * Redireciona a conexao 'platform' para SQLite in-memory
 * e executa as migrations do diretorio platform/.
 */
trait UsesPlatformDatabase
{
    protected function setUpPlatformDatabase(): void
    {
        // Redireciona conexao 'platform' para SQLite in-memory
        config([
            'database.connections.platform' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        // Executa migrations do platform
        $this->artisan('migrate', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--realpath' => false,
        ]);
    }
}
