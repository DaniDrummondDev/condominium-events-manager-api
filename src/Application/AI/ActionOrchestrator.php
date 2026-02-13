<?php

declare(strict_types=1);

namespace Application\AI;

use Application\AI\Contracts\AIActionLogRepositoryInterface;
use Application\AI\DTOs\ActionDTO;
use Application\AI\DTOs\OrchestratorResult;
use Domain\Shared\Exceptions\DomainException;

class ActionOrchestrator
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private AIActionLogRepositoryInterface $actionLogRepository,
    ) {}

    /**
     * @param array<array{name: string, arguments: array<string, mixed>}> $toolCalls
     */
    public function processToolCalls(array $toolCalls, string $tenantUserId): OrchestratorResult
    {
        $readResults = [];
        $proposedActions = [];

        foreach ($toolCalls as $toolCall) {
            $toolName = $toolCall['name'];
            $arguments = $toolCall['arguments'];

            $toolDefinition = $this->toolRegistry->getToolByName($toolName);

            if ($toolDefinition === null) {
                continue;
            }

            if ($this->toolRegistry->requiresConfirmation($toolName)) {
                $actionId = $this->actionLogRepository->create(
                    tenantUserId: $tenantUserId,
                    toolName: $toolName,
                    inputData: $arguments,
                );

                $proposedActions[] = new ActionDTO(
                    id: $actionId,
                    toolName: $toolName,
                    description: $toolDefinition['description'],
                    inputData: $arguments,
                    requiresConfirmation: true,
                );
            } else {
                $handler = $toolDefinition['handler'];
                $result = $handler(...$arguments);
                $readResults[] = is_string($result) ? $result : json_encode($result, JSON_THROW_ON_ERROR);
            }
        }

        return new OrchestratorResult(
            readResults: $readResults,
            proposedActions: $proposedActions,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmAction(string $actionId, string $confirmedBy): array
    {
        $actionLog = $this->actionLogRepository->findById($actionId);

        if ($actionLog === null) {
            throw new DomainException(
                'AI action not found',
                'AI_ACTION_NOT_FOUND',
                ['action_id' => $actionId],
            );
        }

        if ($actionLog->status !== 'proposed') {
            throw new DomainException(
                'AI action is not in proposed status',
                'AI_ACTION_INVALID_STATUS',
                ['action_id' => $actionId, 'current_status' => $actionLog->status],
            );
        }

        $toolDefinition = $this->toolRegistry->getToolByName($actionLog->toolName);

        if ($toolDefinition === null) {
            throw new DomainException(
                'Tool not found in registry',
                'AI_TOOL_NOT_FOUND',
                ['tool_name' => $actionLog->toolName],
            );
        }

        $this->actionLogRepository->updateStatus($actionId, 'confirmed', $confirmedBy);

        try {
            $handler = $toolDefinition['handler'];
            $result = $handler(...$actionLog->inputData);
            $outputData = is_array($result) ? $result : ['result' => $result];

            $this->actionLogRepository->updateStatus(
                $actionId,
                'executed',
                outputData: $outputData,
            );

            return $outputData;
        } catch (\Throwable $e) {
            $this->actionLogRepository->updateStatus(
                $actionId,
                'failed',
                outputData: ['error' => $e->getMessage()],
            );

            throw $e;
        }
    }

    public function rejectAction(string $actionId, ?string $reason = null): void
    {
        $actionLog = $this->actionLogRepository->findById($actionId);

        if ($actionLog === null) {
            throw new DomainException(
                'AI action not found',
                'AI_ACTION_NOT_FOUND',
                ['action_id' => $actionId],
            );
        }

        if ($actionLog->status !== 'proposed') {
            throw new DomainException(
                'AI action is not in proposed status',
                'AI_ACTION_INVALID_STATUS',
                ['action_id' => $actionId, 'current_status' => $actionLog->status],
            );
        }

        $this->actionLogRepository->updateStatus(
            $actionId,
            'rejected',
            outputData: $reason !== null ? ['reason' => $reason] : null,
        );
    }
}
