<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\PlanModel;
use Application\Billing\Contracts\PlanRepositoryInterface;
use Domain\Billing\Entities\Plan;
use Domain\Billing\Enums\PlanStatus;
use Domain\Shared\ValueObjects\Uuid;

class EloquentPlanRepository implements PlanRepositoryInterface
{
    public function findById(Uuid $id): ?Plan
    {
        $model = PlanModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function findBySlug(string $slug): ?Plan
    {
        $model = PlanModel::query()->where('slug', $slug)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<Plan>
     */
    public function findAll(): array
    {
        return PlanModel::query()
            ->get()
            ->map(fn (PlanModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(Plan $plan): void
    {
        PlanModel::query()->updateOrCreate(
            ['id' => $plan->id()->value()],
            [
                'name' => $plan->name(),
                'slug' => $plan->slug(),
                'status' => $plan->status()->value,
            ],
        );
    }

    private function toDomain(PlanModel $model): Plan
    {
        return new Plan(
            id: Uuid::fromString($model->id),
            name: $model->name,
            slug: $model->slug,
            status: PlanStatus::from($model->status),
        );
    }
}
