<?php

declare(strict_types=1);

namespace Application\Unit\Contracts;

use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Unit;

interface UnitRepositoryInterface
{
    public function findById(Uuid $id): ?Unit;

    /**
     * @return array<Unit>
     */
    public function findByBlockId(Uuid $blockId): array;

    public function findByNumber(string $number, ?Uuid $blockId): ?Unit;

    public function countByTenant(): int;

    public function countActiveByTenant(): int;

    public function save(Unit $unit): void;
}
