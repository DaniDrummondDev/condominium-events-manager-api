<?php

declare(strict_types=1);

namespace Application\Communication\DTOs;

final readonly class CreateSupportRequestDTO
{
    public function __construct(
        public string $userId,
        public string $subject,
        public string $category,
        public string $priority,
    ) {}
}
