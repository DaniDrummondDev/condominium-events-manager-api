<?php

declare(strict_types=1);

namespace Application\Governance\DTOs;

final readonly class RuleDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $category,
        public bool $isActive,
        public int $order,
        public string $createdBy,
        public string $createdAt,
    ) {}
}
