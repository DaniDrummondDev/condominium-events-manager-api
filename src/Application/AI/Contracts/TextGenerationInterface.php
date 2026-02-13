<?php

declare(strict_types=1);

namespace Application\AI\Contracts;

use Application\AI\DTOs\TextGenerationResult;
use Prism\Prism\Tool;

interface TextGenerationInterface
{
    /**
     * @param array<array{role: string, content: string}> $messages
     * @param array<Tool> $tools
     */
    public function chat(string $systemPrompt, array $messages, array $tools = []): TextGenerationResult;
}
