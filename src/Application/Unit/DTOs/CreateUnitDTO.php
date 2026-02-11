<?php

declare(strict_types=1);

namespace Application\Unit\DTOs;

final readonly class CreateUnitDTO
{
    public function __construct(
        public ?string $blockId,
        public string $number,
        public ?int $floor,
        public string $type,
    ) {}
}
