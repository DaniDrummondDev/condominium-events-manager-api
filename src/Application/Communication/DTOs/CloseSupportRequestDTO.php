<?php

declare(strict_types=1);

namespace Application\Communication\DTOs;

final readonly class CloseSupportRequestDTO
{
    public function __construct(
        public string $supportRequestId,
        public string $reason,
    ) {}
}
