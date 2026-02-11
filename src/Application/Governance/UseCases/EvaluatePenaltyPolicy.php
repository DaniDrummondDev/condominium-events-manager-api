<?php

declare(strict_types=1);

namespace Application\Governance\UseCases;

use Application\Governance\Contracts\PenaltyPolicyRepositoryInterface;
use Application\Governance\Contracts\ViolationRepositoryInterface;
use Domain\Governance\Enums\ViolationType;

final readonly class EvaluatePenaltyPolicy
{
    private const int VIOLATION_WINDOW_DAYS = 365;

    public function __construct(
        private PenaltyPolicyRepositoryInterface $policyRepository,
        private ViolationRepositoryInterface $violationRepository,
        private ApplyPenalty $applyPenalty,
    ) {}

    public function execute(string $violationId, string $unitId, string $violationType): void
    {
        $type = ViolationType::from($violationType);
        $policies = $this->policyRepository->findByViolationType($type);

        if (count($policies) === 0) {
            return;
        }

        $violationCount = $this->violationRepository->countByUnitAndType(
            \Domain\Shared\ValueObjects\Uuid::fromString($unitId),
            $type,
            self::VIOLATION_WINDOW_DAYS,
        );

        foreach ($policies as $policy) {
            if ($violationCount >= $policy->occurrenceThreshold()) {
                $this->applyPenalty->execute(
                    violationId: $violationId,
                    unitId: $unitId,
                    penaltyType: $policy->penaltyType()->value,
                    blockDays: $policy->blockDays(),
                );

                break; // Apply only the first matching policy
            }
        }
    }
}
