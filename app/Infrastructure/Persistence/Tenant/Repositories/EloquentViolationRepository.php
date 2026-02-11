<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\ViolationModel;
use Application\Governance\Contracts\ViolationRepositoryInterface;
use DateTimeImmutable;
use Domain\Governance\Entities\Violation;
use Domain\Governance\Enums\ViolationSeverity;
use Domain\Governance\Enums\ViolationStatus;
use Domain\Governance\Enums\ViolationType;
use Domain\Shared\ValueObjects\Uuid;

class EloquentViolationRepository implements ViolationRepositoryInterface
{
    public function findById(Uuid $id): ?Violation
    {
        $model = ViolationModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<Violation>
     */
    public function findByUnit(Uuid $unitId): array
    {
        return ViolationModel::query()
            ->where('unit_id', $unitId->value())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (ViolationModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<Violation>
     */
    public function findByResident(Uuid $tenantUserId): array
    {
        return ViolationModel::query()
            ->where('tenant_user_id', $tenantUserId->value())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (ViolationModel $model) => $this->toDomain($model))
            ->all();
    }

    public function countByUnitAndType(Uuid $unitId, ViolationType $type, int $withinDays): int
    {
        return ViolationModel::query()
            ->where('unit_id', $unitId->value())
            ->where('type', $type->value)
            ->where('created_at', '>=', now()->subDays($withinDays))
            ->count();
    }

    public function save(Violation $violation): void
    {
        ViolationModel::query()->updateOrCreate(
            ['id' => $violation->id()->value()],
            [
                'unit_id' => $violation->unitId()->value(),
                'tenant_user_id' => $violation->tenantUserId()?->value(),
                'reservation_id' => $violation->reservationId()?->value(),
                'rule_id' => $violation->ruleId()?->value(),
                'type' => $violation->type()->value,
                'severity' => $violation->severity()->value,
                'description' => $violation->description(),
                'status' => $violation->status()->value,
                'is_automatic' => $violation->isAutomatic(),
                'created_by' => $violation->createdBy()?->value(),
                'upheld_by' => $violation->upheldBy()?->value(),
                'upheld_at' => $violation->upheldAt()?->format('Y-m-d H:i:s'),
                'revoked_by' => $violation->revokedBy()?->value(),
                'revoked_at' => $violation->revokedAt()?->format('Y-m-d H:i:s'),
                'revoked_reason' => $violation->revokedReason(),
            ],
        );
    }

    public function delete(Uuid $id): void
    {
        ViolationModel::query()->where('id', $id->value())->delete();
    }

    private function toDomain(ViolationModel $model): Violation
    {
        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new Violation(
            id: Uuid::fromString($model->id),
            unitId: Uuid::fromString($model->unit_id),
            tenantUserId: $model->tenant_user_id !== null ? Uuid::fromString($model->tenant_user_id) : null,
            reservationId: $model->reservation_id !== null ? Uuid::fromString($model->reservation_id) : null,
            ruleId: $model->rule_id !== null ? Uuid::fromString($model->rule_id) : null,
            type: ViolationType::from($model->type),
            severity: ViolationSeverity::from($model->severity),
            description: $model->description,
            status: ViolationStatus::from($model->status),
            isAutomatic: (bool) $model->is_automatic,
            createdBy: $model->created_by !== null ? Uuid::fromString($model->created_by) : null,
            upheldBy: $model->upheld_by !== null ? Uuid::fromString($model->upheld_by) : null,
            upheldAt: $model->getRawOriginal('upheld_at') !== null ? new DateTimeImmutable($model->getRawOriginal('upheld_at')) : null,
            revokedBy: $model->revoked_by !== null ? Uuid::fromString($model->revoked_by) : null,
            revokedAt: $model->getRawOriginal('revoked_at') !== null ? new DateTimeImmutable($model->getRawOriginal('revoked_at')) : null,
            revokedReason: $model->revoked_reason,
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
