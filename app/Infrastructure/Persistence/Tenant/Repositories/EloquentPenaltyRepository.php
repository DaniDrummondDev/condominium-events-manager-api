<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\PenaltyModel;
use Application\Governance\Contracts\PenaltyRepositoryInterface;
use DateTimeImmutable;
use Domain\Governance\Entities\Penalty;
use Domain\Governance\Enums\PenaltyStatus;
use Domain\Governance\Enums\PenaltyType;
use Domain\Shared\ValueObjects\Uuid;

class EloquentPenaltyRepository implements PenaltyRepositoryInterface
{
    public function findById(Uuid $id): ?Penalty
    {
        $model = PenaltyModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<Penalty>
     */
    public function findByUnit(Uuid $unitId): array
    {
        return PenaltyModel::query()
            ->where('unit_id', $unitId->value())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (PenaltyModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<Penalty>
     */
    public function findActiveByUnit(Uuid $unitId): array
    {
        return PenaltyModel::query()
            ->where('unit_id', $unitId->value())
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->get()
            ->map(fn (PenaltyModel $model) => $this->toDomain($model))
            ->all();
    }

    public function hasActiveBlock(Uuid $unitId): bool
    {
        return PenaltyModel::query()
            ->where('unit_id', $unitId->value())
            ->where('status', 'active')
            ->whereIn('type', ['temporary_block', 'permanent_block'])
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->exists();
    }

    public function save(Penalty $penalty): void
    {
        PenaltyModel::query()->updateOrCreate(
            ['id' => $penalty->id()->value()],
            [
                'violation_id' => $penalty->violationId()->value(),
                'unit_id' => $penalty->unitId()->value(),
                'type' => $penalty->type()->value,
                'starts_at' => $penalty->startsAt()->format('Y-m-d H:i:s'),
                'ends_at' => $penalty->endsAt()?->format('Y-m-d H:i:s'),
                'status' => $penalty->status()->value,
                'revoked_at' => $penalty->revokedAt()?->format('Y-m-d H:i:s'),
                'revoked_by' => $penalty->revokedBy()?->value(),
                'revoked_reason' => $penalty->revokedReason(),
                'created_at' => $penalty->createdAt()->format('Y-m-d H:i:s'),
            ],
        );
    }

    private function toDomain(PenaltyModel $model): Penalty
    {
        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new Penalty(
            id: Uuid::fromString($model->id),
            violationId: Uuid::fromString($model->violation_id),
            unitId: Uuid::fromString($model->unit_id),
            type: PenaltyType::from($model->type),
            startsAt: new DateTimeImmutable($model->getRawOriginal('starts_at')),
            endsAt: $model->getRawOriginal('ends_at') !== null ? new DateTimeImmutable($model->getRawOriginal('ends_at')) : null,
            status: PenaltyStatus::from($model->status),
            revokedAt: $model->getRawOriginal('revoked_at') !== null ? new DateTimeImmutable($model->getRawOriginal('revoked_at')) : null,
            revokedBy: $model->revoked_by !== null ? Uuid::fromString($model->revoked_by) : null,
            revokedReason: $model->revoked_reason,
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
