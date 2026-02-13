<?php

declare(strict_types=1);

use App\Infrastructure\Gateways\AI\PrismEmbeddingGenerator;
use Prism\Prism\Embeddings\Response;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Embedding;
use Prism\Prism\ValueObjects\EmbeddingsUsage;
use Prism\Prism\ValueObjects\Meta;

test('generates embedding for single text', function () {
    $embeddingVector = array_fill(0, 10, 0.1);

    $mockResponse = new Response(
        embeddings: [new Embedding($embeddingVector)],
        usage: new EmbeddingsUsage(tokens: 10),
        meta: new Meta(id: 'emb-1', model: 'text-embedding-3-small'),
    );

    Prism::fake([$mockResponse]);

    config([
        'ai.embedding_provider' => 'openai',
        'ai.embedding_model' => 'text-embedding-3-small',
    ]);

    $generator = new PrismEmbeddingGenerator();
    $result = $generator->generate('test text');

    expect($result)->toBeArray()
        ->and(count($result))->toBe(10)
        ->and($result[0])->toBe(0.1);
});

test('generates batch embeddings', function () {
    $embedding1 = array_fill(0, 5, 0.2);
    $embedding2 = array_fill(0, 5, 0.3);

    $mockResponse = new Response(
        embeddings: [new Embedding($embedding1), new Embedding($embedding2)],
        usage: new EmbeddingsUsage(tokens: 20),
        meta: new Meta(id: 'emb-2', model: 'text-embedding-3-small'),
    );

    Prism::fake([$mockResponse]);

    config([
        'ai.embedding_provider' => 'openai',
        'ai.embedding_model' => 'text-embedding-3-small',
    ]);

    $generator = new PrismEmbeddingGenerator();
    $result = $generator->generateBatch(['text 1', 'text 2']);

    expect($result)->toHaveCount(2)
        ->and($result[0][0])->toBe(0.2)
        ->and($result[1][0])->toBe(0.3);
});

test('returns correct embedding dimensions', function () {
    $dimensions = 1536;
    $embeddingVector = array_fill(0, $dimensions, 0.001);

    $mockResponse = new Response(
        embeddings: [new Embedding($embeddingVector)],
        usage: new EmbeddingsUsage(tokens: 15),
        meta: new Meta(id: 'emb-3', model: 'text-embedding-3-small'),
    );

    Prism::fake([$mockResponse]);

    config([
        'ai.embedding_provider' => 'openai',
        'ai.embedding_model' => 'text-embedding-3-small',
    ]);

    $generator = new PrismEmbeddingGenerator();
    $result = $generator->generate('test with standard dimensions');

    expect($result)->toBeArray()
        ->and(count($result))->toBe($dimensions);
});
