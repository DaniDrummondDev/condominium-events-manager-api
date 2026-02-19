<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use Domain\Billing\Entities\Plan;
use Domain\Shared\ValueObjects\Uuid;

interface PlanRepositoryInterface
{
    public function findById(Uuid $id): ?Plan;

    public function findBySlug(string $slug): ?Plan;

    /**
     * @return array<Plan>
     */
    public function findAll(): array;

    /**
     * @return array<Plan>
     */
    public function findAllActive(): array;

    public function save(Plan $plan): void;
}
