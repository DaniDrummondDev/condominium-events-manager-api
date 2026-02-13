<?php

declare(strict_types=1);

namespace Application\AI\Contracts;

use Application\AI\DTOs\ActionLogDTO;

interface AIActionLogRepositoryInterface
{
    /**
     * @param array<string, mixed> $inputData
     */
    public function create(
        string $tenantUserId,
        string $toolName,
        array $inputData,
        string $status = 'proposed',
    ): string;

    public function findById(string $id): ?ActionLogDTO;

    /**
     * @return array<ActionLogDTO>
     */
    public function findPendingByUser(string $tenantUserId): array;

    /**
     * @param array<string, mixed>|null $outputData
     */
    public function updateStatus(
        string $id,
        string $status,
        ?string $confirmedBy = null,
        ?array $outputData = null,
    ): void;
}
