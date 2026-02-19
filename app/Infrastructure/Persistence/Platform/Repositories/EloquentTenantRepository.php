<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use Application\Tenant\Contracts\TenantRepositoryInterface;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Entities\Tenant;
use Domain\Tenant\Enums\CondominiumType;
use Domain\Tenant\Enums\TenantStatus;

class EloquentTenantRepository implements TenantRepositoryInterface
{
    public function findById(Uuid $id): ?Tenant
    {
        $model = TenantModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function findBySlug(string $slug): ?Tenant
    {
        $model = TenantModel::query()->where('slug', $slug)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Tenant $tenant): void
    {
        TenantModel::query()->updateOrCreate(
            ['id' => $tenant->id()->value()],
            [
                'slug' => $tenant->slug(),
                'name' => $tenant->name(),
                'type' => $tenant->type()->value,
                'status' => $tenant->status()->value,
                'database_name' => $tenant->databaseName(),
            ],
        );
    }

    /**
     * @return array<Tenant>
     */
    public function findAllActive(): array
    {
        return TenantModel::query()
            ->where('status', TenantStatus::Active->value)
            ->get()
            ->map(fn (TenantModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<Tenant>
     */
    public function findAllForMigration(): array
    {
        return TenantModel::query()
            ->whereIn('status', [
                TenantStatus::Active->value,
                TenantStatus::Trial->value,
                TenantStatus::PastDue->value,
            ])
            ->whereNotNull('database_name')
            ->get()
            ->map(fn (TenantModel $model) => $this->toDomain($model))
            ->all();
    }

    public function saveConfig(Uuid $id, ?array $config): void
    {
        TenantModel::query()
            ->where('id', $id->value())
            ->update(['config' => $config !== null ? json_encode($config) : null]);
    }

    private function toDomain(TenantModel $model): Tenant
    {
        return new Tenant(
            id: Uuid::fromString($model->id),
            slug: $model->slug,
            name: $model->name,
            type: CondominiumType::from($model->type),
            status: TenantStatus::from($model->status),
            databaseName: $model->database_name,
        );
    }
}
