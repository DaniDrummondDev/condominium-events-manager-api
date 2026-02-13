<?php

declare(strict_types=1);

namespace Application\AI\DTOs;

final readonly class SuggestRequestDTO
{
    public function __construct(
        public string $context,
        public ?string $spaceId = null,
        public ?string $date = null,
    ) {}
}
