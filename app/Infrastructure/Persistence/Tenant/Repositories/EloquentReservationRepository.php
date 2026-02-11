<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\ReservationModel;
use Application\Reservation\Contracts\ReservationRepositoryInterface;
use DateTimeImmutable;
use Domain\Reservation\Entities\Reservation;
use Domain\Reservation\Enums\ReservationStatus;
use Domain\Shared\ValueObjects\DateRange;
use Domain\Shared\ValueObjects\Uuid;

class EloquentReservationRepository implements ReservationRepositoryInterface
{
    public function findById(Uuid $id): ?Reservation
    {
        $model = ReservationModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<Reservation>
     */
    public function findBySpace(Uuid $spaceId): array
    {
        return ReservationModel::query()
            ->where('space_id', $spaceId->value())
            ->orderBy('start_datetime')
            ->get()
            ->map(fn (ReservationModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<Reservation>
     */
    public function findByUnit(Uuid $unitId): array
    {
        return ReservationModel::query()
            ->where('unit_id', $unitId->value())
            ->orderBy('start_datetime', 'desc')
            ->get()
            ->map(fn (ReservationModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<Reservation>
     */
    public function findByResident(Uuid $residentId): array
    {
        return ReservationModel::query()
            ->where('resident_id', $residentId->value())
            ->orderBy('start_datetime', 'desc')
            ->get()
            ->map(fn (ReservationModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<Reservation>
     */
    public function findConflicting(Uuid $spaceId, DateRange $period, ?Uuid $excludeReservationId = null): array
    {
        $query = ReservationModel::query()
            ->where('space_id', $spaceId->value())
            ->whereIn('status', ['pending_approval', 'confirmed', 'in_progress'])
            ->where('start_datetime', '<', $period->end()->format('Y-m-d H:i:s'))
            ->where('end_datetime', '>', $period->start()->format('Y-m-d H:i:s'))
            ->lockForUpdate();

        if ($excludeReservationId !== null) {
            $query->where('id', '!=', $excludeReservationId->value());
        }

        return $query->get()
            ->map(fn (ReservationModel $model) => $this->toDomain($model))
            ->all();
    }

    public function countMonthlyBySpaceAndUnit(Uuid $spaceId, Uuid $unitId, int $year, int $month): int
    {
        return ReservationModel::query()
            ->where('space_id', $spaceId->value())
            ->where('unit_id', $unitId->value())
            ->whereIn('status', ['pending_approval', 'confirmed', 'in_progress', 'completed'])
            ->whereYear('start_datetime', $year)
            ->whereMonth('start_datetime', $month)
            ->count();
    }

    public function save(Reservation $reservation): void
    {
        ReservationModel::query()->updateOrCreate(
            ['id' => $reservation->id()->value()],
            [
                'space_id' => $reservation->spaceId()->value(),
                'unit_id' => $reservation->unitId()->value(),
                'resident_id' => $reservation->residentId()->value(),
                'status' => $reservation->status()->value,
                'title' => $reservation->title(),
                'start_datetime' => $reservation->startDatetime()->format('Y-m-d H:i:s'),
                'end_datetime' => $reservation->endDatetime()->format('Y-m-d H:i:s'),
                'expected_guests' => $reservation->expectedGuests(),
                'notes' => $reservation->notes(),
                'approved_by' => $reservation->approvedBy()?->value(),
                'approved_at' => $reservation->approvedAt()?->format('Y-m-d H:i:s'),
                'rejected_by' => $reservation->rejectedBy()?->value(),
                'rejected_at' => $reservation->rejectedAt()?->format('Y-m-d H:i:s'),
                'rejection_reason' => $reservation->rejectionReason(),
                'canceled_by' => $reservation->canceledBy()?->value(),
                'canceled_at' => $reservation->canceledAt()?->format('Y-m-d H:i:s'),
                'cancellation_reason' => $reservation->cancellationReason(),
                'completed_at' => $reservation->completedAt()?->format('Y-m-d H:i:s'),
                'no_show_at' => $reservation->noShowAt()?->format('Y-m-d H:i:s'),
                'no_show_by' => $reservation->noShowBy()?->value(),
                'checked_in_at' => $reservation->checkedInAt()?->format('Y-m-d H:i:s'),
            ],
        );
    }

    private function toDomain(ReservationModel $model): Reservation
    {
        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new Reservation(
            id: Uuid::fromString($model->id),
            spaceId: Uuid::fromString($model->space_id),
            unitId: Uuid::fromString($model->unit_id),
            residentId: Uuid::fromString($model->resident_id),
            status: ReservationStatus::from($model->status),
            title: $model->title,
            startDatetime: new DateTimeImmutable($model->getRawOriginal('start_datetime')),
            endDatetime: new DateTimeImmutable($model->getRawOriginal('end_datetime')),
            expectedGuests: (int) $model->expected_guests,
            notes: $model->notes,
            approvedBy: $model->approved_by !== null ? Uuid::fromString($model->approved_by) : null,
            approvedAt: $model->getRawOriginal('approved_at') !== null ? new DateTimeImmutable($model->getRawOriginal('approved_at')) : null,
            rejectedBy: $model->rejected_by !== null ? Uuid::fromString($model->rejected_by) : null,
            rejectedAt: $model->getRawOriginal('rejected_at') !== null ? new DateTimeImmutable($model->getRawOriginal('rejected_at')) : null,
            rejectionReason: $model->rejection_reason,
            canceledBy: $model->canceled_by !== null ? Uuid::fromString($model->canceled_by) : null,
            canceledAt: $model->getRawOriginal('canceled_at') !== null ? new DateTimeImmutable($model->getRawOriginal('canceled_at')) : null,
            cancellationReason: $model->cancellation_reason,
            completedAt: $model->getRawOriginal('completed_at') !== null ? new DateTimeImmutable($model->getRawOriginal('completed_at')) : null,
            noShowAt: $model->getRawOriginal('no_show_at') !== null ? new DateTimeImmutable($model->getRawOriginal('no_show_at')) : null,
            noShowBy: $model->no_show_by !== null ? Uuid::fromString($model->no_show_by) : null,
            checkedInAt: $model->getRawOriginal('checked_in_at') !== null ? new DateTimeImmutable($model->getRawOriginal('checked_in_at')) : null,
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
