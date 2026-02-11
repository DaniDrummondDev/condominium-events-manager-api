<?php

declare(strict_types=1);

namespace Application\Space\Contracts;

use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceBlock;

interface SpaceBlockRepositoryInterface
{
    /**
     * @return array<SpaceBlock>
     */
    public function findBySpaceId(Uuid $spaceId): array;

    /**
     * @return array<SpaceBlock>
     */
    public function findActiveBySpaceId(Uuid $spaceId): array;

    public function findById(Uuid $id): ?SpaceBlock;

    public function save(SpaceBlock $block): void;

    public function delete(Uuid $id): void;
}
