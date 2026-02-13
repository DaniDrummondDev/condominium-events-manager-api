<?php

declare(strict_types=1);

namespace Application\AI\DTOs;

final readonly class EmbeddingResultDTO
{
    public function __construct(
        public string $sourceType,
        public string $sourceId,
        public string $contentText,
        public float $score,
    ) {}
}
