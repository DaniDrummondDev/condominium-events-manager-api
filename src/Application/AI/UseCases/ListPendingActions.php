<?php

declare(strict_types=1);

namespace Application\AI\UseCases;

use Application\AI\Contracts\AIActionLogRepositoryInterface;
use Application\AI\DTOs\ActionLogDTO;

final readonly class ListPendingActions
{
    public function __construct(
        private AIActionLogRepositoryInterface $actionLogRepository,
    ) {}

    /**
     * @return array<ActionLogDTO>
     */
    public function execute(string $tenantUserId): array
    {
        return $this->actionLogRepository->findPendingByUser($tenantUserId);
    }
}
