<?php

declare(strict_types=1);

namespace Application\AI\Contracts;

interface EmbeddingGenerationInterface
{
    /**
     * @return array<float>
     */
    public function generate(string $text): array;

    /**
     * @param array<string> $texts
     * @return array<array<float>>
     */
    public function generateBatch(array $texts): array;
}
