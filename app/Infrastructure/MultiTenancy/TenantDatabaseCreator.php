<?php

declare(strict_types=1);

namespace App\Infrastructure\MultiTenancy;

use Illuminate\Support\Facades\DB;

class TenantDatabaseCreator
{
    /**
     * Cria um database PostgreSQL para o tenant.
     */
    public function createDatabase(string $name): void
    {
        if ($this->databaseExists($name)) {
            return;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $name);

        DB::connection('platform')
            ->statement("CREATE DATABASE \"{$safeName}\"");
    }

    /**
     * Verifica se o database existe.
     */
    public function databaseExists(string $name): bool
    {
        $result = DB::connection('platform')
            ->select(
                'SELECT 1 FROM pg_database WHERE datname = ?',
                [$name],
            );

        return count($result) > 0;
    }

    /**
     * Remove um database PostgreSQL (uso em testes/rollback).
     */
    public function dropDatabase(string $name): void
    {
        if (! $this->databaseExists($name)) {
            return;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $name);

        // Termina conexoes ativas antes de dropar
        DB::connection('platform')
            ->statement("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$safeName}' AND pid <> pg_backend_pid()");

        DB::connection('platform')
            ->statement("DROP DATABASE \"{$safeName}\"");
    }
}
