<?php

declare(strict_types=1);

namespace App\Infrastructure\Gateways\AI;

use Application\AI\Contracts\EmbeddingGenerationInterface;
use Prism\Prism\Facades\Prism;

class PrismEmbeddingGenerator implements EmbeddingGenerationInterface
{
    public function generate(string $text): array
    {
        $response = Prism::embeddings()
            ->using(config('ai.embedding_provider', 'openai'), config('ai.embedding_model', 'text-embedding-3-small'))
            ->fromInput($text)
            ->asEmbeddings();

        return $response->embeddings[0]->embedding;
    }

    public function generateBatch(array $texts): array
    {
        $response = Prism::embeddings()
            ->using(config('ai.embedding_provider', 'openai'), config('ai.embedding_model', 'text-embedding-3-small'))
            ->fromArray($texts)
            ->asEmbeddings();

        return array_map(fn ($e) => $e->embedding, $response->embeddings);
    }
}
