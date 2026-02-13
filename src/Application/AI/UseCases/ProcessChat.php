<?php

declare(strict_types=1);

namespace Application\AI\UseCases;

use Application\AI\ActionOrchestrator;
use Application\AI\Contracts\AIUsageLogRepositoryInterface;
use Application\AI\Contracts\TextGenerationInterface;
use Application\AI\DTOs\ChatRequestDTO;
use Application\AI\DTOs\ChatResponseDTO;
use Application\AI\SessionManager;
use Application\AI\SystemPromptBuilder;
use Application\AI\ToolRegistry;
use Domain\Shared\Exceptions\DomainException;

final readonly class ProcessChat
{
    public function __construct(
        private TextGenerationInterface $textGeneration,
        private ToolRegistry $toolRegistry,
        private ActionOrchestrator $actionOrchestrator,
        private SessionManager $sessionManager,
        private SystemPromptBuilder $systemPromptBuilder,
        private AIUsageLogRepositoryInterface $usageLogRepository,
    ) {}

    public function execute(
        ChatRequestDTO $dto,
        string $tenantUserId,
        string $tenantName,
        string $userName,
        string $userRole,
    ): ChatResponseDTO {
        if (! in_array($userRole, config('ai.allowed_roles', []), true)) {
            throw new DomainException(
                'User role does not have access to AI features',
                'AI_ACCESS_DENIED',
                ['role' => $userRole],
            );
        }

        $sessionId = $this->sessionManager->getOrCreateSession($dto->sessionId, $tenantUserId);

        $this->sessionManager->addMessage($sessionId, 'user', $dto->message);

        $systemPrompt = $this->systemPromptBuilder->build($tenantName, $userName, $userRole);
        $messages = $this->sessionManager->getMessages($sessionId);
        $tools = $this->toolRegistry->getToolsForPrism();

        $startTime = hrtime(true);

        $result = $this->textGeneration->chat($systemPrompt, $messages, $tools);

        $suggestedActions = [];

        if (! empty($result->toolCalls)) {
            $orchestratorResult = $this->actionOrchestrator->processToolCalls($result->toolCalls, $tenantUserId);
            $suggestedActions = $orchestratorResult->proposedActions;

            if (! empty($orchestratorResult->readResults)) {
                $contextMessage = "Resultados das consultas:\n" . implode("\n---\n", $orchestratorResult->readResults);
                $messages[] = ['role' => 'assistant', 'content' => $result->text];
                $messages[] = ['role' => 'user', 'content' => $contextMessage];

                $result = $this->textGeneration->chat($systemPrompt, $messages);
            }
        }

        $latencyMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        $this->sessionManager->addMessage($sessionId, 'assistant', $result->text);

        $this->usageLogRepository->log(
            tenantUserId: $tenantUserId,
            action: 'chat',
            model: config('ai.chat_model', 'gpt-4o'),
            tokensInput: $result->tokensInput,
            tokensOutput: $result->tokensOutput,
            latencyMs: $latencyMs,
        );

        return new ChatResponseDTO(
            response: $result->text,
            sessionId: $sessionId,
            suggestedActions: $suggestedActions,
        );
    }
}
