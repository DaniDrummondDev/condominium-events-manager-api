<?php

declare(strict_types=1);

namespace App\Infrastructure\MultiTenancy;

use DateTimeImmutable;

/**
 * Objeto imutavel com dados do tenant atual.
 * Registrado como singleton no container por request.
 */
final readonly class TenantContext
{
    public function __construct(
        public string $tenantId,
        public string $tenantSlug,
        public string $tenantName,
        public string $tenantType,
        public string $tenantStatus,
        public string $databaseName,
        public DateTimeImmutable $resolvedAt,
    ) {}
}
