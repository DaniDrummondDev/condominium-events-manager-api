<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\PlanVersionModel;
use Application\Billing\Contracts\PlanVersionRepositoryInterface;
use DateTimeImmutable;
use Domain\Billing\Entities\PlanVersion;
use Domain\Billing\Enums\PlanStatus;
use Domain\Shared\ValueObjects\Uuid;

class EloquentPlanVersionRepository implements PlanVersionRepositoryInterface
{
    public function findById(Uuid $id): ?PlanVersion
    {
        $model = PlanVersionModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function findActiveByPlanId(Uuid $planId): ?PlanVersion
    {
        $model = PlanVersionModel::query()
            ->where('plan_id', $planId->value())
            ->where('status', PlanStatus::Active->value)
            ->orderByDesc('version')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findByPlanIdAndVersion(Uuid $planId, int $version): ?PlanVersion
    {
        $model = PlanVersionModel::query()
            ->where('plan_id', $planId->value())
            ->where('version', $version)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function nextVersionNumber(Uuid $planId): int
    {
        $max = PlanVersionModel::query()
            ->where('plan_id', $planId->value())
            ->max('version');

        return ((int) $max) + 1;
    }

    public function save(PlanVersion $planVersion): void
    {
        PlanVersionModel::query()->updateOrCreate(
            ['id' => $planVersion->id()->value()],
            [
                'plan_id' => $planVersion->planId()->value(),
                'version' => $planVersion->version(),
                'status' => $planVersion->status()->value,
                'created_at' => $planVersion->createdAt(),
            ],
        );
    }

    private function toDomain(PlanVersionModel $model): PlanVersion
    {
        return new PlanVersion(
            id: Uuid::fromString($model->id),
            planId: Uuid::fromString($model->plan_id),
            version: (int) $model->version,
            status: PlanStatus::from($model->status),
            createdAt: new DateTimeImmutable((string) $model->created_at),
        );
    }
}
