<?php

declare(strict_types=1);

namespace App\Infrastructure\Gateways\AI;

use Application\AI\Contracts\TextGenerationInterface;
use Application\AI\DTOs\TextGenerationResult;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class PrismTextGenerator implements TextGenerationInterface
{
    public function chat(string $systemPrompt, array $messages, array $tools = []): TextGenerationResult
    {
        $prismMessages = $this->convertMessages($messages);

        $request = Prism::text()
            ->using(config('ai.chat_provider', 'openai'), config('ai.chat_model', 'gpt-4o'))
            ->withSystemPrompt($systemPrompt)
            ->withMessages($prismMessages)
            ->withMaxTokens((int) config('ai.max_tokens', 2048))
            ->usingTemperature((float) config('ai.temperature', 0.7));

        if (! empty($tools)) {
            $request->withTools($tools);
        }

        $response = $request->asText();

        $toolCalls = array_map(fn ($tc) => [
            'name' => $tc->name,
            'arguments' => $tc->arguments(),
        ], $response->toolCalls);

        return new TextGenerationResult(
            text: $response->text,
            toolCalls: $toolCalls,
            tokensInput: $response->usage->promptTokens,
            tokensOutput: $response->usage->completionTokens,
            finishReason: $response->finishReason->value,
        );
    }

    /**
     * @param array<array{role: string, content: string}> $messages
     * @return array<UserMessage|AssistantMessage>
     */
    private function convertMessages(array $messages): array
    {
        $prismMessages = [];

        foreach ($messages as $message) {
            $prismMessages[] = match ($message['role']) {
                'user' => new UserMessage($message['content']),
                'assistant' => new AssistantMessage($message['content']),
                default => new UserMessage($message['content']),
            };
        }

        return $prismMessages;
    }
}
