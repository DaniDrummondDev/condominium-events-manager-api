<?php

declare(strict_types=1);

namespace Application\Space\DTOs;

final readonly class SpaceBlockDTO
{
    public function __construct(
        public string $id,
        public string $spaceId,
        public string $reason,
        public string $startDatetime,
        public string $endDatetime,
        public string $blockedBy,
        public ?string $notes,
        public string $createdAt,
    ) {}
}
