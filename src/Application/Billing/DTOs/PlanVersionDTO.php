<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class PlanVersionDTO
{
    /**
     * @param  array<PlanPriceDTO>  $prices
     */
    public function __construct(
        public string $id,
        public int $version,
        public string $status,
        public string $createdAt,
        public array $prices = [],
    ) {}
}
