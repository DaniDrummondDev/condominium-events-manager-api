<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\AIActionLogModel;
use Application\AI\Contracts\AIActionLogRepositoryInterface;
use Application\AI\DTOs\ActionLogDTO;
use Domain\Shared\ValueObjects\Uuid;

class EloquentAIActionLogRepository implements AIActionLogRepositoryInterface
{
    public function create(
        string $tenantUserId,
        string $toolName,
        array $inputData,
        string $status = 'proposed',
    ): string {
        $id = Uuid::generate()->value();

        AIActionLogModel::query()->create([
            'id' => $id,
            'tenant_user_id' => $tenantUserId,
            'tool_name' => $toolName,
            'input_data' => $inputData,
            'status' => $status,
            'created_at' => now(),
        ]);

        return $id;
    }

    public function findById(string $id): ?ActionLogDTO
    {
        $model = AIActionLogModel::query()->find($id);

        return $model ? $this->toDTO($model) : null;
    }

    public function findPendingByUser(string $tenantUserId): array
    {
        return AIActionLogModel::query()
            ->where('tenant_user_id', $tenantUserId)
            ->where('status', 'proposed')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (AIActionLogModel $model) => $this->toDTO($model))
            ->all();
    }

    public function updateStatus(
        string $id,
        string $status,
        ?string $confirmedBy = null,
        ?array $outputData = null,
    ): void {
        $data = ['status' => $status];

        if ($confirmedBy !== null) {
            $data['confirmed_by'] = $confirmedBy;
        }

        if ($outputData !== null) {
            $data['output_data'] = $outputData;
        }

        if (in_array($status, ['executed', 'failed'], true)) {
            $data['executed_at'] = now();
        }

        AIActionLogModel::query()
            ->where('id', $id)
            ->update($data);
    }

    private function toDTO(AIActionLogModel $model): ActionLogDTO
    {
        return new ActionLogDTO(
            id: $model->id,
            tenantUserId: $model->tenant_user_id,
            toolName: $model->tool_name,
            inputData: $model->input_data ?? [],
            outputData: $model->output_data,
            status: $model->status,
            confirmedBy: $model->confirmed_by,
            executedAt: $model->executed_at?->toIso8601String(),
            createdAt: $model->created_at->toIso8601String(),
        );
    }
}
