<?php

declare(strict_types=1);

namespace Application\Governance\Contracts;

use Domain\Governance\Entities\CondominiumRule;
use Domain\Shared\ValueObjects\Uuid;

interface CondominiumRuleRepositoryInterface
{
    public function findById(Uuid $id): ?CondominiumRule;

    /**
     * @return array<CondominiumRule>
     */
    public function findAll(): array;

    /**
     * @return array<CondominiumRule>
     */
    public function findActive(): array;

    public function save(CondominiumRule $rule): void;

    public function delete(Uuid $id): void;
}
