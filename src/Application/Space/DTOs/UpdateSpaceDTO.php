<?php

declare(strict_types=1);

namespace Application\Space\DTOs;

final readonly class UpdateSpaceDTO
{
    public function __construct(
        public string $spaceId,
        public ?string $name = null,
        public ?string $description = null,
        public ?string $type = null,
        public ?int $capacity = null,
        public ?bool $requiresApproval = null,
        public ?int $maxDurationHours = null,
        public ?int $maxAdvanceDays = null,
        public ?int $minAdvanceHours = null,
        public ?int $cancellationDeadlineHours = null,
    ) {}
}
