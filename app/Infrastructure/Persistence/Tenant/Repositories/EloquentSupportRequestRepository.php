<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\SupportRequestModel;
use Application\Communication\Contracts\SupportRequestRepositoryInterface;
use DateTimeImmutable;
use Domain\Communication\Entities\SupportRequest;
use Domain\Communication\Enums\ClosedReason;
use Domain\Communication\Enums\SupportRequestCategory;
use Domain\Communication\Enums\SupportRequestPriority;
use Domain\Communication\Enums\SupportRequestStatus;
use Domain\Shared\ValueObjects\Uuid;

class EloquentSupportRequestRepository implements SupportRequestRepositoryInterface
{
    public function findById(Uuid $id): ?SupportRequest
    {
        $model = SupportRequestModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<SupportRequest>
     */
    public function findByUser(Uuid $userId): array
    {
        return SupportRequestModel::query()
            ->where('tenant_user_id', $userId->value())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SupportRequestModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<SupportRequest>
     */
    public function findAll(): array
    {
        return SupportRequestModel::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SupportRequestModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(SupportRequest $request): void
    {
        SupportRequestModel::query()->updateOrCreate(
            ['id' => $request->id()->value()],
            [
                'tenant_user_id' => $request->userId()->value(),
                'subject' => $request->subject(),
                'category' => $request->category()->value,
                'status' => $request->status()->value,
                'priority' => $request->priority()->value,
                'closed_at' => $request->closedAt()?->format('Y-m-d H:i:s'),
                'closed_reason' => $request->closedReason()?->value,
            ],
        );
    }

    private function toDomain(SupportRequestModel $model): SupportRequest
    {
        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');
        /** @var string $updatedAtRaw */
        $updatedAtRaw = $model->getRawOriginal('updated_at');

        return new SupportRequest(
            id: Uuid::fromString($model->id),
            userId: Uuid::fromString($model->tenant_user_id),
            subject: $model->subject,
            category: SupportRequestCategory::from($model->category),
            status: SupportRequestStatus::from($model->status),
            priority: SupportRequestPriority::from($model->priority),
            closedAt: $model->getRawOriginal('closed_at') !== null ? new DateTimeImmutable($model->getRawOriginal('closed_at')) : null,
            closedReason: $model->closed_reason !== null ? ClosedReason::from($model->closed_reason) : null,
            createdAt: new DateTimeImmutable($createdAtRaw),
            updatedAt: new DateTimeImmutable($updatedAtRaw),
        );
    }
}
