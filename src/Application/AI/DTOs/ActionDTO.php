<?php

declare(strict_types=1);

namespace Application\AI\DTOs;

final readonly class ActionDTO
{
    /**
     * @param array<string, mixed> $inputData
     */
    public function __construct(
        public string $id,
        public string $toolName,
        public string $description,
        public array $inputData,
        public bool $requiresConfirmation,
    ) {}
}
