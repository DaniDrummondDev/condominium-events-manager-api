<?php

declare(strict_types=1);

namespace Application\Unit\DTOs;

final readonly class UpdateUnitDTO
{
    public function __construct(
        public string $unitId,
        public ?string $number,
        public ?int $floor,
        public ?string $type,
    ) {}
}
