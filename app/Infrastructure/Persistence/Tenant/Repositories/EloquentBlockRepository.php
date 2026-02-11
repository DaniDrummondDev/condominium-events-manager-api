<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\BlockModel;
use Application\Unit\Contracts\BlockRepositoryInterface;
use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Block;
use Domain\Unit\Enums\BlockStatus;

class EloquentBlockRepository implements BlockRepositoryInterface
{
    public function findById(Uuid $id): ?Block
    {
        $model = BlockModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function findByIdentifier(string $identifier): ?Block
    {
        $model = BlockModel::query()->where('identifier', $identifier)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<Block>
     */
    public function findAllActive(): array
    {
        return BlockModel::query()
            ->where('status', 'active')
            ->get()
            ->map(fn (BlockModel $model) => $this->toDomain($model))
            ->all();
    }

    public function countByTenant(): int
    {
        return BlockModel::query()->count();
    }

    public function save(Block $block): void
    {
        BlockModel::query()->updateOrCreate(
            ['id' => $block->id()->value()],
            [
                'identifier' => $block->identifier(),
                'name' => $block->name(),
                'floors' => $block->floors(),
                'status' => $block->status()->value,
            ],
        );
    }

    private function toDomain(BlockModel $model): Block
    {
        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new Block(
            id: Uuid::fromString($model->id),
            name: $model->name,
            identifier: $model->identifier,
            floors: $model->floors !== null ? (int) $model->floors : null,
            status: BlockStatus::from($model->status),
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
