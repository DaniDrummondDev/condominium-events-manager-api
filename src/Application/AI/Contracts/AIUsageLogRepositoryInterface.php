<?php

declare(strict_types=1);

namespace Application\AI\Contracts;

interface AIUsageLogRepositoryInterface
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function log(
        string $tenantUserId,
        string $action,
        string $model,
        int $tokensInput,
        int $tokensOutput,
        int $latencyMs,
        ?array $metadata = null,
    ): void;
}
