<?php

declare(strict_types=1);

namespace Application\AI\DTOs;

final readonly class OrchestratorResult
{
    /**
     * @param array<string> $readResults
     * @param array<ActionDTO> $proposedActions
     */
    public function __construct(
        public array $readResults = [],
        public array $proposedActions = [],
    ) {}
}
