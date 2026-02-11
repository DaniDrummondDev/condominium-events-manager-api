<?php

declare(strict_types=1);

namespace Application\Unit\DTOs;

final readonly class UnitDTO
{
    public function __construct(
        public string $id,
        public ?string $blockId,
        public string $number,
        public ?int $floor,
        public string $type,
        public string $status,
        public bool $isOccupied,
        public string $createdAt,
    ) {}
}
