<?php

declare(strict_types=1);

namespace Application\AI\DTOs;

final readonly class ChatRequestDTO
{
    public function __construct(
        public string $message,
        public ?string $sessionId = null,
    ) {}
}
