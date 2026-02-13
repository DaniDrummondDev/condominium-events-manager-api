<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\AnnouncementReadModel;
use Application\Communication\Contracts\AnnouncementReadRepositoryInterface;
use Domain\Communication\Entities\AnnouncementRead;
use Domain\Shared\ValueObjects\Uuid;

class EloquentAnnouncementReadRepository implements AnnouncementReadRepositoryInterface
{
    public function existsByAnnouncementAndUser(Uuid $announcementId, Uuid $userId): bool
    {
        return AnnouncementReadModel::query()
            ->where('announcement_id', $announcementId->value())
            ->where('tenant_user_id', $userId->value())
            ->exists();
    }

    public function countByAnnouncement(Uuid $announcementId): int
    {
        return AnnouncementReadModel::query()
            ->where('announcement_id', $announcementId->value())
            ->count();
    }

    public function save(AnnouncementRead $read): void
    {
        AnnouncementReadModel::query()->updateOrCreate(
            ['id' => $read->id()->value()],
            [
                'announcement_id' => $read->announcementId()->value(),
                'tenant_user_id' => $read->userId()->value(),
                'read_at' => $read->readAt()->format('Y-m-d H:i:s'),
            ],
        );
    }
}
