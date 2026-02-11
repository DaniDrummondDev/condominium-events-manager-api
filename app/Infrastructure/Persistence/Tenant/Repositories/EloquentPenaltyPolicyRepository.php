<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\PenaltyPolicyModel;
use Application\Governance\Contracts\PenaltyPolicyRepositoryInterface;
use DateTimeImmutable;
use Domain\Governance\Entities\PenaltyPolicy;
use Domain\Governance\Enums\PenaltyType;
use Domain\Governance\Enums\ViolationType;
use Domain\Shared\ValueObjects\Uuid;

class EloquentPenaltyPolicyRepository implements PenaltyPolicyRepositoryInterface
{
    public function findById(Uuid $id): ?PenaltyPolicy
    {
        $model = PenaltyPolicyModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<PenaltyPolicy>
     */
    public function findAll(): array
    {
        return PenaltyPolicyModel::query()
            ->get()
            ->map(fn (PenaltyPolicyModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<PenaltyPolicy>
     */
    public function findActive(): array
    {
        return PenaltyPolicyModel::query()
            ->where('is_active', true)
            ->get()
            ->map(fn (PenaltyPolicyModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<PenaltyPolicy>
     */
    public function findByViolationType(ViolationType $type): array
    {
        return PenaltyPolicyModel::query()
            ->where('violation_type', $type->value)
            ->where('is_active', true)
            ->get()
            ->map(fn (PenaltyPolicyModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(PenaltyPolicy $policy): void
    {
        PenaltyPolicyModel::query()->updateOrCreate(
            ['id' => $policy->id()->value()],
            [
                'violation_type' => $policy->violationType()->value,
                'occurrence_threshold' => $policy->occurrenceThreshold(),
                'penalty_type' => $policy->penaltyType()->value,
                'block_days' => $policy->blockDays(),
                'is_active' => $policy->isActive(),
            ],
        );
    }

    public function delete(Uuid $id): void
    {
        PenaltyPolicyModel::query()->where('id', $id->value())->delete();
    }

    private function toDomain(PenaltyPolicyModel $model): PenaltyPolicy
    {
        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new PenaltyPolicy(
            id: Uuid::fromString($model->id),
            violationType: ViolationType::from($model->violation_type),
            occurrenceThreshold: (int) $model->occurrence_threshold,
            penaltyType: PenaltyType::from($model->penalty_type),
            blockDays: $model->block_days !== null ? (int) $model->block_days : null,
            isActive: (bool) $model->is_active,
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
