<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class PlanDTO
{
    /**
     * @param  array<array{feature_key: string, value: string, type: string}>  $features
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $status,
        public ?PlanVersionDTO $currentVersion = null,
        public array $features = [],
    ) {}
}
