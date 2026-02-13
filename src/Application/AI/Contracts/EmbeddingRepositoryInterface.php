<?php

declare(strict_types=1);

namespace Application\AI\Contracts;

use Application\AI\DTOs\EmbeddingResultDTO;

interface EmbeddingRepositoryInterface
{
    /**
     * @param array<float> $embedding
     * @param array<string, mixed>|null $metadata
     */
    public function store(
        string $sourceType,
        string $sourceId,
        int $chunkIndex,
        string $text,
        array $embedding,
        string $modelVersion,
        string $contentHash,
        ?array $metadata = null,
    ): void;

    /**
     * @param array<float> $embedding
     * @return array<EmbeddingResultDTO>
     */
    public function searchSimilar(array $embedding, int $limit = 5, ?string $sourceType = null): array;

    public function deleteBySource(string $sourceType, string $sourceId): void;
}
