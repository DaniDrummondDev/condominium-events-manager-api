<?php

declare(strict_types=1);

namespace Application\AI\UseCases;

use Application\AI\ActionOrchestrator;

final readonly class RejectAction
{
    public function __construct(
        private ActionOrchestrator $actionOrchestrator,
    ) {}

    public function execute(string $actionId, ?string $reason = null): void
    {
        $this->actionOrchestrator->rejectAction($actionId, $reason);
    }
}
