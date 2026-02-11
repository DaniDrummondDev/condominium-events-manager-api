<?php

declare(strict_types=1);

namespace Application\Space\Contracts;

use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;

interface SpaceRepositoryInterface
{
    public function findById(Uuid $id): ?Space;

    public function findByName(string $name): ?Space;

    /**
     * @return array<Space>
     */
    public function findAllActive(): array;

    public function countActiveByTenant(): int;

    public function save(Space $space): void;
}
