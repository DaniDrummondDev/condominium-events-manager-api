<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\AIUsageLogModel;
use Application\AI\Contracts\AIUsageLogRepositoryInterface;
use Domain\Shared\ValueObjects\Uuid;

class EloquentAIUsageLogRepository implements AIUsageLogRepositoryInterface
{
    public function log(
        string $tenantUserId,
        string $action,
        string $model,
        int $tokensInput,
        int $tokensOutput,
        int $latencyMs,
        ?array $metadata = null,
    ): void {
        AIUsageLogModel::query()->create([
            'id' => Uuid::generate()->value(),
            'tenant_user_id' => $tenantUserId,
            'action' => $action,
            'model' => $model,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'latency_ms' => $latencyMs,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
