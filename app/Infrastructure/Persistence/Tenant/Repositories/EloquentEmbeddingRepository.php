<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\AIEmbeddingModel;
use Application\AI\Contracts\EmbeddingRepositoryInterface;
use Application\AI\DTOs\EmbeddingResultDTO;
use Illuminate\Support\Facades\DB;

class EloquentEmbeddingRepository implements EmbeddingRepositoryInterface
{
    public function store(
        string $sourceType,
        string $sourceId,
        int $chunkIndex,
        string $text,
        array $embedding,
        string $modelVersion,
        string $contentHash,
        ?array $metadata = null,
    ): void {
        AIEmbeddingModel::query()->updateOrCreate(
            [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'chunk_index' => $chunkIndex,
                'model_version' => $modelVersion,
            ],
            [
                'content_text' => $text,
                'embedding' => json_encode($embedding),
                'content_hash' => $contentHash,
                'metadata' => $metadata,
                'created_at' => now(),
            ],
        );
    }

    public function searchSimilar(array $embedding, int $limit = 5, ?string $sourceType = null): array
    {
        $connection = DB::connection('tenant');

        if ($connection->getDriverName() !== 'pgsql') {
            return [];
        }

        $vectorString = '[' . implode(',', $embedding) . ']';

        $query = $connection->table('ai_embeddings')
            ->selectRaw('source_type, source_id, content_text, (embedding <=> ?::vector) AS distance', [$vectorString]);

        if ($sourceType !== null) {
            $query->where('source_type', $sourceType);
        }

        $results = $query->orderBy('distance')
            ->limit($limit)
            ->get();

        return $results->map(fn (object $row) => new EmbeddingResultDTO(
            sourceType: $row->source_type,
            sourceId: $row->source_id,
            contentText: $row->content_text,
            score: 1.0 - (float) $row->distance,
        ))->all();
    }

    public function deleteBySource(string $sourceType, string $sourceId): void
    {
        AIEmbeddingModel::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->delete();
    }
}
