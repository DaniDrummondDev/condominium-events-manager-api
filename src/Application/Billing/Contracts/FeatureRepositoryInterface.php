<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use Domain\Billing\Entities\Feature;
use Domain\Shared\ValueObjects\Uuid;

interface FeatureRepositoryInterface
{
    public function findById(Uuid $id): ?Feature;

    public function findByCode(string $code): ?Feature;

    /**
     * @return array<Feature>
     */
    public function findAll(): array;

    public function save(Feature $feature): void;
}
