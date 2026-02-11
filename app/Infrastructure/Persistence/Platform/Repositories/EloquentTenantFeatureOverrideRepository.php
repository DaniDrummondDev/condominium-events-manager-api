<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\TenantFeatureOverrideModel;
use Application\Billing\Contracts\TenantFeatureOverrideRepositoryInterface;
use DateTimeImmutable;
use Domain\Billing\Entities\TenantFeatureOverride;
use Domain\Shared\ValueObjects\Uuid;

class EloquentTenantFeatureOverrideRepository implements TenantFeatureOverrideRepositoryInterface
{
    /**
     * @return array<TenantFeatureOverride>
     */
    public function findByTenantId(Uuid $tenantId): array
    {
        return TenantFeatureOverrideModel::query()
            ->where('tenant_id', $tenantId->value())
            ->get()
            ->map(fn (TenantFeatureOverrideModel $model) => $this->toDomain($model))
            ->all();
    }

    public function findByTenantAndFeature(Uuid $tenantId, Uuid $featureId): ?TenantFeatureOverride
    {
        $model = TenantFeatureOverrideModel::query()
            ->where('tenant_id', $tenantId->value())
            ->where('feature_id', $featureId->value())
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(TenantFeatureOverride $override): void
    {
        TenantFeatureOverrideModel::query()->updateOrCreate(
            ['id' => $override->id()->value()],
            [
                'tenant_id' => $override->tenantId()->value(),
                'feature_id' => $override->featureId()->value(),
                'value' => $override->value(),
                'reason' => $override->reason(),
                'expires_at' => $override->expiresAt(),
                'created_by' => $override->createdBy()->value(),
            ],
        );
    }

    public function delete(Uuid $id): void
    {
        TenantFeatureOverrideModel::query()
            ->where('id', $id->value())
            ->delete();
    }

    private function toDomain(TenantFeatureOverrideModel $model): TenantFeatureOverride
    {
        return new TenantFeatureOverride(
            id: Uuid::fromString($model->id),
            tenantId: Uuid::fromString($model->tenant_id),
            featureId: Uuid::fromString($model->feature_id),
            value: $model->value,
            reason: $model->reason,
            expiresAt: $model->expires_at ? new DateTimeImmutable((string) $model->expires_at) : null,
            createdBy: Uuid::fromString($model->created_by),
            createdAt: new DateTimeImmutable((string) $model->created_at),
        );
    }
}
