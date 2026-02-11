<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\SpaceBlockModel;
use Application\Space\Contracts\SpaceBlockRepositoryInterface;
use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceBlock;

class EloquentSpaceBlockRepository implements SpaceBlockRepositoryInterface
{
    /**
     * @return array<SpaceBlock>
     */
    public function findBySpaceId(Uuid $spaceId): array
    {
        return SpaceBlockModel::query()
            ->where('space_id', $spaceId->value())
            ->get()
            ->map(fn (SpaceBlockModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<SpaceBlock>
     */
    public function findActiveBySpaceId(Uuid $spaceId): array
    {
        return SpaceBlockModel::query()
            ->where('space_id', $spaceId->value())
            ->where('end_datetime', '>', now())
            ->get()
            ->map(fn (SpaceBlockModel $model) => $this->toDomain($model))
            ->all();
    }

    public function findById(Uuid $id): ?SpaceBlock
    {
        $model = SpaceBlockModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function save(SpaceBlock $block): void
    {
        SpaceBlockModel::query()->updateOrCreate(
            ['id' => $block->id()->value()],
            [
                'space_id' => $block->spaceId()->value(),
                'reason' => $block->reason(),
                'start_datetime' => $block->startDatetime()->format('Y-m-d H:i:s'),
                'end_datetime' => $block->endDatetime()->format('Y-m-d H:i:s'),
                'blocked_by' => $block->blockedBy()->value(),
                'notes' => $block->notes(),
                'created_at' => $block->createdAt()->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function delete(Uuid $id): void
    {
        SpaceBlockModel::query()->where('id', $id->value())->delete();
    }

    private function toDomain(SpaceBlockModel $model): SpaceBlock
    {
        /** @var string $startRaw */
        $startRaw = $model->getRawOriginal('start_datetime');

        /** @var string $endRaw */
        $endRaw = $model->getRawOriginal('end_datetime');

        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new SpaceBlock(
            id: Uuid::fromString($model->id),
            spaceId: Uuid::fromString($model->space_id),
            reason: $model->reason,
            startDatetime: new DateTimeImmutable($startRaw),
            endDatetime: new DateTimeImmutable($endRaw),
            blockedBy: Uuid::fromString($model->blocked_by),
            notes: $model->notes,
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
