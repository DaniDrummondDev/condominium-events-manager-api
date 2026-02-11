<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\PlanFeatureModel;
use Application\Billing\Contracts\PlanFeatureRepositoryInterface;
use Domain\Billing\Entities\PlanFeature;
use Domain\Billing\Enums\FeatureType;
use Domain\Shared\ValueObjects\Uuid;

class EloquentPlanFeatureRepository implements PlanFeatureRepositoryInterface
{
    /**
     * @return array<PlanFeature>
     */
    public function findByPlanVersionId(Uuid $planVersionId): array
    {
        return PlanFeatureModel::query()
            ->where('plan_version_id', $planVersionId->value())
            ->get()
            ->map(fn (PlanFeatureModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(PlanFeature $planFeature): void
    {
        PlanFeatureModel::query()->updateOrCreate(
            ['id' => $planFeature->id()->value()],
            [
                'plan_version_id' => $planFeature->planVersionId()->value(),
                'feature_key' => $planFeature->featureKey(),
                'value' => $planFeature->value(),
                'type' => $planFeature->type()->value,
            ],
        );
    }

    /**
     * @param  array<PlanFeature>  $planFeatures
     */
    public function saveMany(array $planFeatures): void
    {
        foreach ($planFeatures as $planFeature) {
            $this->save($planFeature);
        }
    }

    private function toDomain(PlanFeatureModel $model): PlanFeature
    {
        return new PlanFeature(
            id: Uuid::fromString($model->id),
            planVersionId: Uuid::fromString($model->plan_version_id),
            featureKey: $model->feature_key,
            value: $model->value,
            type: FeatureType::from($model->type),
        );
    }
}
