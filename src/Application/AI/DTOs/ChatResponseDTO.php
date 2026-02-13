<?php

declare(strict_types=1);

namespace Application\AI\DTOs;

final readonly class ChatResponseDTO
{
    /**
     * @param array<ActionDTO> $suggestedActions
     */
    public function __construct(
        public string $response,
        public string $sessionId,
        public array $suggestedActions = [],
    ) {}
}
