<?php

declare(strict_types=1);

namespace Application\Unit\DTOs;

final readonly class BlockDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $identifier,
        public ?int $floors,
        public string $status,
        public string $createdAt,
    ) {}
}
