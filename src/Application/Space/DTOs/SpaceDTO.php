<?php

declare(strict_types=1);

namespace Application\Space\DTOs;

final readonly class SpaceDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public string $type,
        public string $status,
        public int $capacity,
        public bool $requiresApproval,
        public ?int $maxDurationHours,
        public int $maxAdvanceDays,
        public int $minAdvanceHours,
        public int $cancellationDeadlineHours,
        public string $createdAt,
    ) {}
}
