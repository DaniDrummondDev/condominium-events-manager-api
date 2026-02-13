<?php

declare(strict_types=1);

namespace Application\AI\DTOs;

final readonly class ActionLogDTO
{
    /**
     * @param array<string, mixed> $inputData
     * @param array<string, mixed>|null $outputData
     */
    public function __construct(
        public string $id,
        public string $tenantUserId,
        public string $toolName,
        public array $inputData,
        public ?array $outputData,
        public string $status,
        public ?string $confirmedBy,
        public ?string $executedAt,
        public string $createdAt,
    ) {}
}
