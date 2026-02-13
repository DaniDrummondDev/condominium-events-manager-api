<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\AnnouncementModel;
use Application\Communication\Contracts\AnnouncementRepositoryInterface;
use DateTimeImmutable;
use Domain\Communication\Entities\Announcement;
use Domain\Communication\Enums\AnnouncementPriority;
use Domain\Communication\Enums\AnnouncementStatus;
use Domain\Communication\Enums\AudienceType;
use Domain\Shared\ValueObjects\Uuid;

class EloquentAnnouncementRepository implements AnnouncementRepositoryInterface
{
    public function findById(Uuid $id): ?Announcement
    {
        $model = AnnouncementModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<Announcement>
     */
    public function findAll(): array
    {
        return AnnouncementModel::query()
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (AnnouncementModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(Announcement $announcement): void
    {
        AnnouncementModel::query()->updateOrCreate(
            ['id' => $announcement->id()->value()],
            [
                'title' => $announcement->title(),
                'body' => $announcement->body(),
                'priority' => $announcement->priority()->value,
                'audience_type' => $announcement->audienceType()->value,
                'audience_ids' => $announcement->audienceIds(),
                'status' => $announcement->status()->value,
                'published_by' => $announcement->publishedBy()->value(),
                'published_at' => $announcement->publishedAt()->format('Y-m-d H:i:s'),
                'expires_at' => $announcement->expiresAt()?->format('Y-m-d H:i:s'),
            ],
        );
    }

    private function toDomain(AnnouncementModel $model): Announcement
    {
        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new Announcement(
            id: Uuid::fromString($model->id),
            title: $model->title,
            body: $model->body,
            priority: AnnouncementPriority::from($model->priority),
            audienceType: AudienceType::from($model->audience_type),
            audienceIds: $model->audience_ids,
            status: AnnouncementStatus::from($model->status),
            publishedBy: Uuid::fromString($model->published_by),
            publishedAt: new DateTimeImmutable($model->getRawOriginal('published_at')),
            expiresAt: $model->getRawOriginal('expires_at') !== null ? new DateTimeImmutable($model->getRawOriginal('expires_at')) : null,
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
