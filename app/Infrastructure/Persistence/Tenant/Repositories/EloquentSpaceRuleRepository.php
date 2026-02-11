<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\SpaceRuleModel;
use Application\Space\Contracts\SpaceRuleRepositoryInterface;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceRule;

class EloquentSpaceRuleRepository implements SpaceRuleRepositoryInterface
{
    /**
     * @return array<SpaceRule>
     */
    public function findBySpaceId(Uuid $spaceId): array
    {
        return SpaceRuleModel::query()
            ->where('space_id', $spaceId->value())
            ->get()
            ->map(fn (SpaceRuleModel $model) => $this->toDomain($model))
            ->all();
    }

    public function findBySpaceIdAndKey(Uuid $spaceId, string $ruleKey): ?SpaceRule
    {
        $model = SpaceRuleModel::query()
            ->where('space_id', $spaceId->value())
            ->where('rule_key', $ruleKey)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findById(Uuid $id): ?SpaceRule
    {
        $model = SpaceRuleModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function save(SpaceRule $rule): void
    {
        SpaceRuleModel::query()->updateOrCreate(
            ['id' => $rule->id()->value()],
            [
                'space_id' => $rule->spaceId()->value(),
                'rule_key' => $rule->ruleKey(),
                'rule_value' => $rule->ruleValue(),
                'description' => $rule->description(),
            ],
        );
    }

    public function delete(Uuid $id): void
    {
        SpaceRuleModel::query()->where('id', $id->value())->delete();
    }

    private function toDomain(SpaceRuleModel $model): SpaceRule
    {
        return new SpaceRule(
            id: Uuid::fromString($model->id),
            spaceId: Uuid::fromString($model->space_id),
            ruleKey: $model->rule_key,
            ruleValue: $model->rule_value,
            description: $model->description,
        );
    }
}
