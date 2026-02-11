<?php

declare(strict_types=1);

namespace Application\Governance\UseCases;

use Application\Governance\Contracts\PenaltyRepositoryInterface;

final readonly class CheckActivePenalties
{
    public function __construct(
        private PenaltyRepositoryInterface $penaltyRepository,
    ) {}

    public function hasActiveBlock(string $unitId): bool
    {
        return $this->penaltyRepository->hasActiveBlock(
            \Domain\Shared\ValueObjects\Uuid::fromString($unitId),
        );
    }
}
