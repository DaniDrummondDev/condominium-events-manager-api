<?php

declare(strict_types=1);

use App\Infrastructure\Gateways\AI\PrismTextGenerator;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

afterEach(fn () => Mockery::close());

test('converts Prism response to TextGenerationResult', function () {
    $mockResponse = new Response(
        steps: collect([]),
        text: 'AI response text',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(promptTokens: 100, completionTokens: 50),
        meta: new Meta(id: 'resp-1', model: 'gpt-4o'),
        messages: collect([]),
        additionalContent: [],
    );

    Prism::fake([$mockResponse]);

    config([
        'ai.chat_provider' => 'openai',
        'ai.chat_model' => 'gpt-4o',
        'ai.max_tokens' => 2048,
        'ai.temperature' => 0.7,
    ]);

    $generator = new PrismTextGenerator();

    $result = $generator->chat(
        systemPrompt: 'You are helpful.',
        messages: [
            ['role' => 'user', 'content' => 'Hello'],
        ],
    );

    expect($result->text)->toBe('AI response text')
        ->and($result->toolCalls)->toBeEmpty()
        ->and($result->tokensInput)->toBe(100)
        ->and($result->tokensOutput)->toBe(50)
        ->and($result->finishReason)->toBe('stop');
});

test('maps assistant messages correctly', function () {
    $mockResponse = new Response(
        steps: collect([]),
        text: 'Response after context',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(promptTokens: 200, completionTokens: 80),
        meta: new Meta(id: 'resp-2', model: 'gpt-4o'),
        messages: collect([]),
        additionalContent: [],
    );

    Prism::fake([$mockResponse]);

    config([
        'ai.chat_provider' => 'openai',
        'ai.chat_model' => 'gpt-4o',
        'ai.max_tokens' => 2048,
        'ai.temperature' => 0.7,
    ]);

    $generator = new PrismTextGenerator();

    $result = $generator->chat(
        systemPrompt: 'You are helpful.',
        messages: [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
            ['role' => 'user', 'content' => 'How are you?'],
        ],
    );

    expect($result->text)->toBe('Response after context')
        ->and($result->tokensInput)->toBe(200)
        ->and($result->tokensOutput)->toBe(80)
        ->and($result->finishReason)->toBe('stop');
});

test('handles finish reason length', function () {
    $mockResponse = new Response(
        steps: collect([]),
        text: 'Truncated response...',
        finishReason: FinishReason::Length,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(promptTokens: 500, completionTokens: 2048),
        meta: new Meta(id: 'resp-3', model: 'gpt-4o'),
        messages: collect([]),
        additionalContent: [],
    );

    Prism::fake([$mockResponse]);

    config([
        'ai.chat_provider' => 'openai',
        'ai.chat_model' => 'gpt-4o',
        'ai.max_tokens' => 2048,
        'ai.temperature' => 0.7,
    ]);

    $generator = new PrismTextGenerator();

    $result = $generator->chat(
        systemPrompt: 'You are helpful.',
        messages: [
            ['role' => 'user', 'content' => 'Write a very long essay'],
        ],
    );

    expect($result->finishReason)->toBe('length')
        ->and($result->text)->toBe('Truncated response...');
});

test('defaults unknown message roles to user messages', function () {
    $mockResponse = new Response(
        steps: collect([]),
        text: 'Handled unknown role',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(promptTokens: 50, completionTokens: 25),
        meta: new Meta(id: 'resp-4', model: 'gpt-4o'),
        messages: collect([]),
        additionalContent: [],
    );

    Prism::fake([$mockResponse]);

    config([
        'ai.chat_provider' => 'openai',
        'ai.chat_model' => 'gpt-4o',
        'ai.max_tokens' => 2048,
        'ai.temperature' => 0.7,
    ]);

    $generator = new PrismTextGenerator();

    $result = $generator->chat(
        systemPrompt: 'You are helpful.',
        messages: [
            ['role' => 'unknown_role', 'content' => 'Some message'],
        ],
    );

    expect($result->text)->toBe('Handled unknown role')
        ->and($result->finishReason)->toBe('stop');
});
