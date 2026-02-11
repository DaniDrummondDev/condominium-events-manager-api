<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\ResidentModel;
use Application\Unit\Contracts\ResidentRepositoryInterface;
use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Resident;
use Domain\Unit\Enums\ResidentRole;
use Domain\Unit\Enums\ResidentStatus;

class EloquentResidentRepository implements ResidentRepositoryInterface
{
    public function findById(Uuid $id): ?Resident
    {
        $model = ResidentModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<Resident>
     */
    public function findByUnitId(Uuid $unitId): array
    {
        return ResidentModel::query()
            ->where('unit_id', $unitId->value())
            ->get()
            ->map(fn (ResidentModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<Resident>
     */
    public function findActiveByUnitId(Uuid $unitId): array
    {
        return ResidentModel::query()
            ->where('unit_id', $unitId->value())
            ->where('status', 'active')
            ->whereNull('moved_out_at')
            ->get()
            ->map(fn (ResidentModel $model) => $this->toDomain($model))
            ->all();
    }

    public function countActiveByUnitId(Uuid $unitId): int
    {
        return ResidentModel::query()
            ->where('unit_id', $unitId->value())
            ->where('status', 'active')
            ->whereNull('moved_out_at')
            ->count();
    }

    /**
     * @return array<Resident>
     */
    public function findByTenantUserId(Uuid $tenantUserId): array
    {
        return ResidentModel::query()
            ->where('tenant_user_id', $tenantUserId->value())
            ->get()
            ->map(fn (ResidentModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(Resident $resident): void
    {
        ResidentModel::query()->updateOrCreate(
            ['id' => $resident->id()->value()],
            [
                'unit_id' => $resident->unitId()->value(),
                'tenant_user_id' => $resident->tenantUserId()->value(),
                'role_in_unit' => $resident->roleInUnit()->value,
                'is_primary' => $resident->isPrimary(),
                'status' => $resident->status()->value,
                'moved_in_at' => $resident->movedInAt()->format('Y-m-d H:i:s'),
                'moved_out_at' => $resident->movedOutAt()?->format('Y-m-d H:i:s'),
            ],
        );
    }

    private function toDomain(ResidentModel $model): Resident
    {
        /** @var string $movedInAtRaw */
        $movedInAtRaw = $model->getRawOriginal('moved_in_at');

        /** @var string|null $movedOutAtRaw */
        $movedOutAtRaw = $model->getRawOriginal('moved_out_at');

        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        $tenantUser = $model->tenantUser;

        return new Resident(
            id: Uuid::fromString($model->id),
            unitId: Uuid::fromString($model->unit_id),
            tenantUserId: Uuid::fromString($model->tenant_user_id),
            name: $tenantUser->name ?? '',
            email: $tenantUser->email ?? '',
            phone: $tenantUser->phone ?? null,
            roleInUnit: ResidentRole::from($model->role_in_unit),
            isPrimary: (bool) $model->is_primary,
            status: ResidentStatus::from($model->status),
            movedInAt: new DateTimeImmutable($movedInAtRaw),
            movedOutAt: $movedOutAtRaw !== null ? new DateTimeImmutable($movedOutAtRaw) : null,
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
