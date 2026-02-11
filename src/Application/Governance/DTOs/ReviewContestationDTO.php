<?php

declare(strict_types=1);

namespace Application\Governance\DTOs;

final readonly class ReviewContestationDTO
{
    public function __construct(
        public string $contestationId,
        public string $respondedBy,
        public bool $accepted,
        public string $response,
    ) {}
}
