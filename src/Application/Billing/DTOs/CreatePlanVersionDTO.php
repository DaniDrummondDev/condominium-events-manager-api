<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class CreatePlanVersionDTO
{
    /**
     * @param  array<array{feature_key: string, value: string, type: string}>  $features
     */
    public function __construct(
        public string $planId,
        public int $priceInCents,
        public string $currency,
        public string $billingCycle,
        public int $trialDays,
        public array $features = [],
    ) {}
}
