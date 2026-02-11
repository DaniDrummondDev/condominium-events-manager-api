<?php

declare(strict_types=1);

namespace Application\Governance\UseCases;

use Application\Governance\Contracts\PenaltyPolicyRepositoryInterface;
use Application\Governance\DTOs\CreatePenaltyPolicyDTO;
use Application\Governance\DTOs\PenaltyPolicyDTO;
use Application\Governance\DTOs\UpdatePenaltyPolicyDTO;
use Domain\Governance\Entities\PenaltyPolicy;
use Domain\Governance\Enums\PenaltyType;
use Domain\Governance\Enums\ViolationType;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ConfigurePenaltyPolicy
{
    public function __construct(
        private PenaltyPolicyRepositoryInterface $policyRepository,
    ) {}

    public function create(CreatePenaltyPolicyDTO $dto): PenaltyPolicyDTO
    {
        $policy = PenaltyPolicy::create(
            id: Uuid::generate(),
            violationType: ViolationType::from($dto->violationType),
            occurrenceThreshold: $dto->occurrenceThreshold,
            penaltyType: PenaltyType::from($dto->penaltyType),
            blockDays: $dto->blockDays,
        );

        $this->policyRepository->save($policy);

        return self::toDTO($policy);
    }

    public function update(UpdatePenaltyPolicyDTO $dto): PenaltyPolicyDTO
    {
        $policy = $this->policyRepository->findById(Uuid::fromString($dto->policyId));

        if ($policy === null) {
            throw new DomainException(
                'Penalty policy not found',
                'PENALTY_POLICY_NOT_FOUND',
                ['policy_id' => $dto->policyId],
            );
        }

        $policy->update(
            occurrenceThreshold: $dto->occurrenceThreshold ?? $policy->occurrenceThreshold(),
            penaltyType: $dto->penaltyType !== null ? PenaltyType::from($dto->penaltyType) : $policy->penaltyType(),
            blockDays: $dto->blockDays ?? $policy->blockDays(),
        );

        $this->policyRepository->save($policy);

        return self::toDTO($policy);
    }

    public function delete(string $policyId): void
    {
        $policy = $this->policyRepository->findById(Uuid::fromString($policyId));

        if ($policy === null) {
            throw new DomainException(
                'Penalty policy not found',
                'PENALTY_POLICY_NOT_FOUND',
                ['policy_id' => $policyId],
            );
        }

        $this->policyRepository->delete($policy->id());
    }

    public static function toDTO(PenaltyPolicy $policy): PenaltyPolicyDTO
    {
        return new PenaltyPolicyDTO(
            id: $policy->id()->value(),
            violationType: $policy->violationType()->value,
            occurrenceThreshold: $policy->occurrenceThreshold(),
            penaltyType: $policy->penaltyType()->value,
            blockDays: $policy->blockDays(),
            isActive: $policy->isActive(),
            createdAt: $policy->createdAt()->format('c'),
        );
    }
}
