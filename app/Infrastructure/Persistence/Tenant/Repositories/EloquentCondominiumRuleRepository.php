<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\CondominiumRuleModel;
use Application\Governance\Contracts\CondominiumRuleRepositoryInterface;
use DateTimeImmutable;
use Domain\Governance\Entities\CondominiumRule;
use Domain\Shared\ValueObjects\Uuid;

class EloquentCondominiumRuleRepository implements CondominiumRuleRepositoryInterface
{
    public function findById(Uuid $id): ?CondominiumRule
    {
        $model = CondominiumRuleModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<CondominiumRule>
     */
    public function findAll(): array
    {
        return CondominiumRuleModel::query()
            ->orderBy('order')
            ->get()
            ->map(fn (CondominiumRuleModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<CondominiumRule>
     */
    public function findActive(): array
    {
        return CondominiumRuleModel::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(fn (CondominiumRuleModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(CondominiumRule $rule): void
    {
        CondominiumRuleModel::query()->updateOrCreate(
            ['id' => $rule->id()->value()],
            [
                'title' => $rule->title(),
                'description' => $rule->description(),
                'category' => $rule->category(),
                'is_active' => $rule->isActive(),
                'order' => $rule->order(),
                'created_by' => $rule->createdBy()->value(),
            ],
        );
    }

    public function delete(Uuid $id): void
    {
        CondominiumRuleModel::query()->where('id', $id->value())->delete();
    }

    private function toDomain(CondominiumRuleModel $model): CondominiumRule
    {
        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new CondominiumRule(
            id: Uuid::fromString($model->id),
            title: $model->title,
            description: $model->description,
            category: $model->category,
            isActive: (bool) $model->is_active,
            order: (int) $model->order,
            createdBy: Uuid::fromString($model->created_by),
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
