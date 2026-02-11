<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\GatewayEventModel;
use Application\Billing\Contracts\GatewayEventRepositoryInterface;
use Application\Billing\DTOs\GatewayEventRecord;

class EloquentGatewayEventRepository implements GatewayEventRepositoryInterface
{
    public function findByIdempotencyKey(string $idempotencyKey): ?GatewayEventRecord
    {
        $model = GatewayEventModel::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($model === null) {
            return null;
        }

        return new GatewayEventRecord(
            id: $model->id,
            gateway: $model->gateway,
            eventType: $model->event_type,
            payload: $model->payload ?? [],
            idempotencyKey: $model->idempotency_key,
            processedAt: $model->processed_at
                ? new \DateTimeImmutable((string) $model->processed_at)
                : null,
        );
    }

    public function save(GatewayEventRecord $record): void
    {
        GatewayEventModel::query()->updateOrCreate(
            ['id' => $record->id],
            [
                'gateway' => $record->gateway,
                'event_type' => $record->eventType,
                'payload' => $record->payload,
                'idempotency_key' => $record->idempotencyKey,
                'processed' => $record->processedAt !== null,
                'processed_at' => $record->processedAt,
            ],
        );
    }
}
