<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\SupportMessageModel;
use Application\Communication\Contracts\SupportMessageRepositoryInterface;
use DateTimeImmutable;
use Domain\Communication\Entities\SupportMessage;
use Domain\Shared\ValueObjects\Uuid;

class EloquentSupportMessageRepository implements SupportMessageRepositoryInterface
{
    /**
     * @return array<SupportMessage>
     */
    public function findByRequest(Uuid $supportRequestId): array
    {
        return SupportMessageModel::query()
            ->where('support_request_id', $supportRequestId->value())
            ->orderBy('created_at')
            ->get()
            ->map(fn (SupportMessageModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(SupportMessage $message): void
    {
        SupportMessageModel::query()->updateOrCreate(
            ['id' => $message->id()->value()],
            [
                'support_request_id' => $message->supportRequestId()->value(),
                'sender_id' => $message->senderId()->value(),
                'body' => $message->body(),
                'is_internal' => $message->isInternal(),
                'created_at' => $message->createdAt()->format('Y-m-d H:i:s'),
            ],
        );
    }

    private function toDomain(SupportMessageModel $model): SupportMessage
    {
        return new SupportMessage(
            id: Uuid::fromString($model->id),
            supportRequestId: Uuid::fromString($model->support_request_id),
            senderId: Uuid::fromString($model->sender_id),
            body: $model->body,
            isInternal: (bool) $model->is_internal,
            createdAt: new DateTimeImmutable($model->getRawOriginal('created_at')),
        );
    }
}
