<?php

declare(strict_types=1);

namespace Application\People\DTOs;

final readonly class DenyGuestDTO
{
    public function __construct(
        public string $guestId,
        public string $deniedBy,
        public string $reason,
    ) {}
}
