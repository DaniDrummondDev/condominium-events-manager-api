<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\ViolationContestationModel;
use Application\Governance\Contracts\ViolationContestationRepositoryInterface;
use DateTimeImmutable;
use Domain\Governance\Entities\ViolationContestation;
use Domain\Governance\Enums\ContestationStatus;
use Domain\Shared\ValueObjects\Uuid;

class EloquentViolationContestationRepository implements ViolationContestationRepositoryInterface
{
    public function findById(Uuid $id): ?ViolationContestation
    {
        $model = ViolationContestationModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function findByViolation(Uuid $violationId): ?ViolationContestation
    {
        $model = ViolationContestationModel::query()
            ->where('violation_id', $violationId->value())
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<ViolationContestation>
     */
    public function findAll(): array
    {
        return ViolationContestationModel::query()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (ViolationContestationModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(ViolationContestation $contestation): void
    {
        ViolationContestationModel::query()->updateOrCreate(
            ['id' => $contestation->id()->value()],
            [
                'violation_id' => $contestation->violationId()->value(),
                'tenant_user_id' => $contestation->tenantUserId()->value(),
                'reason' => $contestation->reason(),
                'status' => $contestation->status()->value,
                'response' => $contestation->response(),
                'responded_by' => $contestation->respondedBy()?->value(),
                'responded_at' => $contestation->respondedAt()?->format('Y-m-d H:i:s'),
                'created_at' => $contestation->createdAt()->format('Y-m-d H:i:s'),
            ],
        );
    }

    private function toDomain(ViolationContestationModel $model): ViolationContestation
    {
        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new ViolationContestation(
            id: Uuid::fromString($model->id),
            violationId: Uuid::fromString($model->violation_id),
            tenantUserId: Uuid::fromString($model->tenant_user_id),
            reason: $model->reason,
            status: ContestationStatus::from($model->status),
            response: $model->response,
            respondedBy: $model->responded_by !== null ? Uuid::fromString($model->responded_by) : null,
            respondedAt: $model->getRawOriginal('responded_at') !== null ? new DateTimeImmutable($model->getRawOriginal('responded_at')) : null,
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
