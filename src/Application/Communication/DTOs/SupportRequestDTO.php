<?php

declare(strict_types=1);

namespace Application\Communication\DTOs;

final readonly class SupportRequestDTO
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $subject,
        public string $category,
        public string $status,
        public string $priority,
        public ?string $closedAt,
        public ?string $closedReason,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
