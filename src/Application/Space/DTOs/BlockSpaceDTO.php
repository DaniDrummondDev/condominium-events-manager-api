<?php

declare(strict_types=1);

namespace Application\Space\DTOs;

final readonly class BlockSpaceDTO
{
    public function __construct(
        public string $spaceId,
        public string $reason,
        public string $startDatetime,
        public string $endDatetime,
        public string $blockedBy,
        public ?string $notes = null,
    ) {}
}
