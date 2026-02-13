<?php

declare(strict_types=1);

namespace Application\AI\UseCases;

use Application\AI\Contracts\AIUsageLogRepositoryInterface;
use Application\AI\Contracts\TextGenerationInterface;
use Application\AI\DTOs\ChatResponseDTO;
use Application\AI\DTOs\SuggestRequestDTO;
use Domain\Shared\Exceptions\DomainException;

final readonly class ProcessSuggestion
{
    public function __construct(
        private TextGenerationInterface $textGeneration,
        private AIUsageLogRepositoryInterface $usageLogRepository,
    ) {}

    public function execute(
        SuggestRequestDTO $dto,
        string $tenantUserId,
        string $tenantName,
        string $userRole,
    ): ChatResponseDTO {
        if (! in_array($userRole, config('ai.allowed_roles', []), true)) {
            throw new DomainException(
                'User role does not have access to AI features',
                'AI_ACCESS_DENIED',
                ['role' => $userRole],
            );
        }

        $prompt = $this->buildSuggestionPrompt($dto, $tenantName);

        $startTime = hrtime(true);

        $result = $this->textGeneration->chat($prompt, [
            ['role' => 'user', 'content' => "Contexto: {$dto->context}"],
        ]);

        $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        $this->usageLogRepository->log(
            tenantUserId: $tenantUserId,
            action: 'suggest',
            model: config('ai.chat_model', 'gpt-4o'),
            tokensInput: $result->tokensInput,
            tokensOutput: $result->tokensOutput,
            latencyMs: $latencyMs,
        );

        return new ChatResponseDTO(
            response: $result->text,
            sessionId: '',
            suggestedActions: [],
        );
    }

    private function buildSuggestionPrompt(SuggestRequestDTO $dto, string $tenantName): string
    {
        $parts = ["Você é o assistente do condomínio \"{$tenantName}\"."];
        $parts[] = "Gere sugestões úteis baseadas no contexto fornecido.";
        $parts[] = "Responda em português brasileiro de forma objetiva.";

        if ($dto->spaceId !== null) {
            $parts[] = "Espaço de referência: {$dto->spaceId}";
        }

        if ($dto->date !== null) {
            $parts[] = "Data de referência: {$dto->date}";
        }

        return implode("\n", $parts);
    }
}
