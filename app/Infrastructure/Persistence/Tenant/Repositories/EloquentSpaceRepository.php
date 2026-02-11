<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\SpaceModel;
use Application\Space\Contracts\SpaceRepositoryInterface;
use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Enums\SpaceStatus;
use Domain\Space\Enums\SpaceType;

class EloquentSpaceRepository implements SpaceRepositoryInterface
{
    public function findById(Uuid $id): ?Space
    {
        $model = SpaceModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function findByName(string $name): ?Space
    {
        $model = SpaceModel::query()->where('name', $name)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<Space>
     */
    public function findAllActive(): array
    {
        return SpaceModel::query()
            ->where('status', 'active')
            ->get()
            ->map(fn (SpaceModel $model) => $this->toDomain($model))
            ->all();
    }

    public function countActiveByTenant(): int
    {
        return SpaceModel::query()
            ->where('status', 'active')
            ->count();
    }

    public function save(Space $space): void
    {
        SpaceModel::query()->updateOrCreate(
            ['id' => $space->id()->value()],
            [
                'name' => $space->name(),
                'description' => $space->description(),
                'type' => $space->type()->value,
                'status' => $space->status()->value,
                'capacity' => $space->capacity(),
                'requires_approval' => $space->requiresApproval(),
                'max_duration_hours' => $space->maxDurationHours(),
                'max_advance_days' => $space->maxAdvanceDays(),
                'min_advance_hours' => $space->minAdvanceHours(),
                'cancellation_deadline_hours' => $space->cancellationDeadlineHours(),
            ],
        );
    }

    private function toDomain(SpaceModel $model): Space
    {
        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new Space(
            id: Uuid::fromString($model->id),
            name: $model->name,
            description: $model->description,
            type: SpaceType::from($model->type),
            status: SpaceStatus::from($model->status),
            capacity: (int) $model->capacity,
            requiresApproval: (bool) $model->requires_approval,
            maxDurationHours: $model->max_duration_hours !== null ? (int) $model->max_duration_hours : null,
            maxAdvanceDays: (int) $model->max_advance_days,
            minAdvanceHours: (int) $model->min_advance_hours,
            cancellationDeadlineHours: (int) $model->cancellation_deadline_hours,
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
