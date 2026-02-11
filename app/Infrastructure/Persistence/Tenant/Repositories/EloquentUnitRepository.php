<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\UnitModel;
use Application\Unit\Contracts\UnitRepositoryInterface;
use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Unit;
use Domain\Unit\Enums\UnitStatus;
use Domain\Unit\Enums\UnitType;

class EloquentUnitRepository implements UnitRepositoryInterface
{
    public function findById(Uuid $id): ?Unit
    {
        $model = UnitModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<Unit>
     */
    public function findByBlockId(Uuid $blockId): array
    {
        return UnitModel::query()
            ->where('block_id', $blockId->value())
            ->get()
            ->map(fn (UnitModel $model) => $this->toDomain($model))
            ->all();
    }

    public function findByNumber(string $number, ?Uuid $blockId): ?Unit
    {
        $query = UnitModel::query()->where('number', $number);

        if ($blockId !== null) {
            $query->where('block_id', $blockId->value());
        } else {
            $query->whereNull('block_id');
        }

        $model = $query->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function countByTenant(): int
    {
        return UnitModel::query()->count();
    }

    public function countActiveByTenant(): int
    {
        return UnitModel::query()->where('status', 'active')->count();
    }

    public function save(Unit $unit): void
    {
        UnitModel::query()->updateOrCreate(
            ['id' => $unit->id()->value()],
            [
                'block_id' => $unit->blockId()?->value(),
                'number' => $unit->number(),
                'floor' => $unit->floor(),
                'type' => $unit->type()->value,
                'status' => $unit->status()->value,
                'is_occupied' => $unit->isOccupied(),
            ],
        );
    }

    private function toDomain(UnitModel $model): Unit
    {
        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new Unit(
            id: Uuid::fromString($model->id),
            blockId: $model->block_id !== null ? Uuid::fromString($model->block_id) : null,
            number: $model->number,
            floor: $model->floor !== null ? (int) $model->floor : null,
            type: UnitType::from($model->type),
            status: UnitStatus::from($model->status),
            isOccupied: (bool) $model->is_occupied,
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
