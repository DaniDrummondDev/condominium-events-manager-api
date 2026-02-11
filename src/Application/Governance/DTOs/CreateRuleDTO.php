<?php

declare(strict_types=1);

namespace Application\Governance\DTOs;

final readonly class CreateRuleDTO
{
    public function __construct(
        public string $title,
        public string $description,
        public string $category,
        public int $order,
        public string $createdBy,
    ) {}
}
