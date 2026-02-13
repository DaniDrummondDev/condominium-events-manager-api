<?php

declare(strict_types=1);

namespace Application\AI\DTOs;

final readonly class TextGenerationResult
{
    /**
     * @param array<array{name: string, arguments: array<string, mixed>}> $toolCalls
     */
    public function __construct(
        public string $text,
        public array $toolCalls,
        public int $tokensInput,
        public int $tokensOutput,
        public string $finishReason,
    ) {}
}
