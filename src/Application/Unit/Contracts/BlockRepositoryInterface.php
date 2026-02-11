<?php

declare(strict_types=1);

namespace Application\Unit\Contracts;

use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Block;

interface BlockRepositoryInterface
{
    public function findById(Uuid $id): ?Block;

    public function findByIdentifier(string $identifier): ?Block;

    /**
     * @return array<Block>
     */
    public function findAllActive(): array;

    public function countByTenant(): int;

    public function save(Block $block): void;
}
