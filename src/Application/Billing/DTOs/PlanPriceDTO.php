<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class PlanPriceDTO
{
    public function __construct(
        public string $id,
        public string $billingCycle,
        public int $priceInCents,
        public string $currency,
        public int $trialDays,
    ) {}
}
