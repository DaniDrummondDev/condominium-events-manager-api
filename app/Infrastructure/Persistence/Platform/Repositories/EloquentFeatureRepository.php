<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\FeatureModel;
use Application\Billing\Contracts\FeatureRepositoryInterface;
use Domain\Billing\Entities\Feature;
use Domain\Billing\Enums\FeatureType;
use Domain\Shared\ValueObjects\Uuid;

class EloquentFeatureRepository implements FeatureRepositoryInterface
{
    public function findById(Uuid $id): ?Feature
    {
        $model = FeatureModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function findByCode(string $code): ?Feature
    {
        $model = FeatureModel::query()->where('code', $code)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<Feature>
     */
    public function findAll(): array
    {
        return FeatureModel::query()
            ->get()
            ->map(fn (FeatureModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(Feature $feature): void
    {
        FeatureModel::query()->updateOrCreate(
            ['id' => $feature->id()->value()],
            [
                'code' => $feature->code(),
                'name' => $feature->name(),
                'type' => $feature->type()->value,
                'description' => $feature->description(),
            ],
        );
    }

    private function toDomain(FeatureModel $model): Feature
    {
        return new Feature(
            id: Uuid::fromString($model->id),
            code: $model->code,
            name: $model->name,
            type: FeatureType::from($model->type),
            description: $model->description,
        );
    }
}
