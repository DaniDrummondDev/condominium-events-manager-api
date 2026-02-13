<?php

declare(strict_types=1);

namespace Application\AI\UseCases;

use Application\AI\ActionOrchestrator;
use Application\AI\Contracts\AIActionLogRepositoryInterface;
use Domain\Shared\Exceptions\DomainException;

final readonly class ConfirmAction
{
    public function __construct(
        private AIActionLogRepositoryInterface $actionLogRepository,
        private ActionOrchestrator $actionOrchestrator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $actionId, string $confirmedBy): array
    {
        $actionLog = $this->actionLogRepository->findById($actionId);

        if ($actionLog === null) {
            throw new DomainException(
                'AI action not found',
                'AI_ACTION_NOT_FOUND',
                ['action_id' => $actionId],
            );
        }

        return $this->actionOrchestrator->confirmAction($actionId, $confirmedBy);
    }
}
