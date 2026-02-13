<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\GuestModel;
use Application\People\Contracts\GuestRepositoryInterface;
use DateTimeImmutable;
use Domain\People\Entities\Guest;
use Domain\People\Enums\GuestStatus;
use Domain\Shared\ValueObjects\Uuid;

class EloquentGuestRepository implements GuestRepositoryInterface
{
    public function findById(Uuid $id): ?Guest
    {
        $model = GuestModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<Guest>
     */
    public function findByReservation(Uuid $reservationId): array
    {
        return GuestModel::query()
            ->where('reservation_id', $reservationId->value())
            ->orderBy('created_at')
            ->get()
            ->map(fn (GuestModel $model) => $this->toDomain($model))
            ->all();
    }

    public function countByReservation(Uuid $reservationId): int
    {
        return GuestModel::query()
            ->where('reservation_id', $reservationId->value())
            ->whereNotIn('status', ['denied', 'no_show'])
            ->count();
    }

    public function save(Guest $guest): void
    {
        GuestModel::query()->updateOrCreate(
            ['id' => $guest->id()->value()],
            [
                'reservation_id' => $guest->reservationId()->value(),
                'name' => $guest->name(),
                'document' => $guest->document(),
                'phone' => $guest->phone(),
                'vehicle_plate' => $guest->vehiclePlate(),
                'relationship' => $guest->relationship(),
                'status' => $guest->status()->value,
                'checked_in_at' => $guest->checkedInAt()?->format('Y-m-d H:i:s'),
                'checked_out_at' => $guest->checkedOutAt()?->format('Y-m-d H:i:s'),
                'checked_in_by' => $guest->checkedInBy()?->value(),
                'denied_by' => $guest->deniedBy()?->value(),
                'denied_reason' => $guest->deniedReason(),
                'registered_by' => $guest->registeredBy()->value(),
            ],
        );
    }

    private function toDomain(GuestModel $model): Guest
    {
        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new Guest(
            id: Uuid::fromString($model->id),
            reservationId: Uuid::fromString($model->reservation_id),
            name: $model->name,
            document: $model->document,
            phone: $model->phone,
            vehiclePlate: $model->vehicle_plate,
            relationship: $model->relationship,
            status: GuestStatus::from($model->status),
            checkedInAt: $model->getRawOriginal('checked_in_at') !== null ? new DateTimeImmutable($model->getRawOriginal('checked_in_at')) : null,
            checkedOutAt: $model->getRawOriginal('checked_out_at') !== null ? new DateTimeImmutable($model->getRawOriginal('checked_out_at')) : null,
            checkedInBy: $model->checked_in_by !== null ? Uuid::fromString($model->checked_in_by) : null,
            deniedBy: $model->denied_by !== null ? Uuid::fromString($model->denied_by) : null,
            deniedReason: $model->denied_reason,
            registeredBy: Uuid::fromString($model->registered_by),
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
