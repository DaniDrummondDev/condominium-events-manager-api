<?php

declare(strict_types=1);

namespace Application\Governance\Contracts;

use Domain\Governance\Entities\PenaltyPolicy;
use Domain\Governance\Enums\ViolationType;
use Domain\Shared\ValueObjects\Uuid;

interface PenaltyPolicyRepositoryInterface
{
    public function findById(Uuid $id): ?PenaltyPolicy;

    /**
     * @return array<PenaltyPolicy>
     */
    public function findAll(): array;

    /**
     * @return array<PenaltyPolicy>
     */
    public function findActive(): array;

    /**
     * Find active policies matching a specific violation type.
     * Used for automatic penalty matching.
     *
     * @return array<PenaltyPolicy>
     */
    public function findByViolationType(ViolationType $type): array;

    public function save(PenaltyPolicy $policy): void;

    public function delete(Uuid $id): void;
}
