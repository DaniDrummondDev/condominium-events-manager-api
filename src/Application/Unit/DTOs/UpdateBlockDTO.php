<?php

declare(strict_types=1);

namespace Application\Unit\DTOs;

final readonly class UpdateBlockDTO
{
    public function __construct(
        public string $blockId,
        public ?string $name,
        public ?string $identifier,
        public ?int $floors,
    ) {}
}
