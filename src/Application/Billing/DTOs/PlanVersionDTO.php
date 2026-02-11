<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class PlanVersionDTO
{
    public function __construct(
        public string $id,
        public int $version,
        public int $priceInCents,
        public string $currency,
        public string $billingCycle,
        public int $trialDays,
        public string $status,
        public string $createdAt,
    ) {}
}
