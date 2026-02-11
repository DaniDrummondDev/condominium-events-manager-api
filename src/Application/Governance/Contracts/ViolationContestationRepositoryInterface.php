<?php

declare(strict_types=1);

namespace Application\Governance\Contracts;

use Domain\Governance\Entities\ViolationContestation;
use Domain\Shared\ValueObjects\Uuid;

interface ViolationContestationRepositoryInterface
{
    public function findById(Uuid $id): ?ViolationContestation;

    public function findByViolation(Uuid $violationId): ?ViolationContestation;

    /**
     * @return array<ViolationContestation>
     */
    public function findAll(): array;

    public function save(ViolationContestation $contestation): void;
}
