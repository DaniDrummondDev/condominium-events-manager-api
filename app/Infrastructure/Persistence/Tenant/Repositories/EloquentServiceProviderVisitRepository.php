<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\ServiceProviderVisitModel;
use Application\People\Contracts\ServiceProviderVisitRepositoryInterface;
use DateTimeImmutable;
use Domain\People\Entities\ServiceProviderVisit;
use Domain\People\Enums\ServiceProviderVisitStatus;
use Domain\Shared\ValueObjects\Uuid;

class EloquentServiceProviderVisitRepository implements ServiceProviderVisitRepositoryInterface
{
    public function findById(Uuid $id): ?ServiceProviderVisit
    {
        $model = ServiceProviderVisitModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<ServiceProviderVisit>
     */
    public function findByServiceProvider(Uuid $serviceProviderId): array
    {
        return ServiceProviderVisitModel::query()
            ->where('service_provider_id', $serviceProviderId->value())
            ->orderBy('scheduled_date', 'desc')
            ->get()
            ->map(fn (ServiceProviderVisitModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<ServiceProviderVisit>
     */
    public function findByUnit(Uuid $unitId): array
    {
        return ServiceProviderVisitModel::query()
            ->where('unit_id', $unitId->value())
            ->orderBy('scheduled_date', 'desc')
            ->get()
            ->map(fn (ServiceProviderVisitModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(ServiceProviderVisit $visit): void
    {
        ServiceProviderVisitModel::query()->updateOrCreate(
            ['id' => $visit->id()->value()],
            [
                'service_provider_id' => $visit->serviceProviderId()->value(),
                'unit_id' => $visit->unitId()->value(),
                'reservation_id' => $visit->reservationId()?->value(),
                'scheduled_date' => $visit->scheduledDate()->format('Y-m-d'),
                'purpose' => $visit->purpose(),
                'status' => $visit->status()->value,
                'checked_in_at' => $visit->checkedInAt()?->format('Y-m-d H:i:s'),
                'checked_out_at' => $visit->checkedOutAt()?->format('Y-m-d H:i:s'),
                'checked_in_by' => $visit->checkedInBy()?->value(),
                'notes' => $visit->notes(),
            ],
        );
    }

    private function toDomain(ServiceProviderVisitModel $model): ServiceProviderVisit
    {
        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new ServiceProviderVisit(
            id: Uuid::fromString($model->id),
            serviceProviderId: Uuid::fromString($model->service_provider_id),
            unitId: Uuid::fromString($model->unit_id),
            reservationId: $model->reservation_id !== null ? Uuid::fromString($model->reservation_id) : null,
            scheduledDate: new DateTimeImmutable($model->getRawOriginal('scheduled_date')),
            purpose: $model->purpose,
            status: ServiceProviderVisitStatus::from($model->status),
            checkedInAt: $model->getRawOriginal('checked_in_at') !== null ? new DateTimeImmutable($model->getRawOriginal('checked_in_at')) : null,
            checkedOutAt: $model->getRawOriginal('checked_out_at') !== null ? new DateTimeImmutable($model->getRawOriginal('checked_out_at')) : null,
            checkedInBy: $model->checked_in_by !== null ? Uuid::fromString($model->checked_in_by) : null,
            notes: $model->notes,
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
