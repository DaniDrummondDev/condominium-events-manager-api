<?php

declare(strict_types=1);

namespace Application\Tenant\Contracts;

use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Entities\Tenant;

interface TenantRepositoryInterface
{
    public function findById(Uuid $id): ?Tenant;

    public function findBySlug(string $slug): ?Tenant;

    public function save(Tenant $tenant): void;

    /**
     * @return array<Tenant>
     */
    public function findAllActive(): array;

    /**
     * @return array<Tenant>
     */
    public function findAllForMigration(): array;

    /**
     * @param array<string, mixed>|null $config
     */
    public function saveConfig(Uuid $id, ?array $config): void;
}
