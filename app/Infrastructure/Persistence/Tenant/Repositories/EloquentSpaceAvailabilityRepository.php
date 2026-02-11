<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\SpaceAvailabilityModel;
use Application\Space\Contracts\SpaceAvailabilityRepositoryInterface;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceAvailability;

class EloquentSpaceAvailabilityRepository implements SpaceAvailabilityRepositoryInterface
{
    /**
     * @return array<SpaceAvailability>
     */
    public function findBySpaceId(Uuid $spaceId): array
    {
        return SpaceAvailabilityModel::query()
            ->where('space_id', $spaceId->value())
            ->get()
            ->map(fn (SpaceAvailabilityModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<SpaceAvailability>
     */
    public function findBySpaceIdAndDay(Uuid $spaceId, int $dayOfWeek): array
    {
        return SpaceAvailabilityModel::query()
            ->where('space_id', $spaceId->value())
            ->where('day_of_week', $dayOfWeek)
            ->get()
            ->map(fn (SpaceAvailabilityModel $model) => $this->toDomain($model))
            ->all();
    }

    public function findById(Uuid $id): ?SpaceAvailability
    {
        $model = SpaceAvailabilityModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function save(SpaceAvailability $availability): void
    {
        SpaceAvailabilityModel::query()->updateOrCreate(
            ['id' => $availability->id()->value()],
            [
                'space_id' => $availability->spaceId()->value(),
                'day_of_week' => $availability->dayOfWeek(),
                'start_time' => $availability->startTime(),
                'end_time' => $availability->endTime(),
            ],
        );
    }

    public function delete(Uuid $id): void
    {
        SpaceAvailabilityModel::query()->where('id', $id->value())->delete();
    }

    private function toDomain(SpaceAvailabilityModel $model): SpaceAvailability
    {
        return new SpaceAvailability(
            id: Uuid::fromString($model->id),
            spaceId: Uuid::fromString($model->space_id),
            dayOfWeek: (int) $model->day_of_week,
            startTime: $model->start_time,
            endTime: $model->end_time,
        );
    }
}
