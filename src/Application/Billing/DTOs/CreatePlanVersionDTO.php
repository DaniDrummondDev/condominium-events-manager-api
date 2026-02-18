<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class CreatePlanVersionDTO
{
    /**
     * @param  array<array{billing_cycle: string, price_in_cents: int, currency: string, trial_days: int}>  $prices
     * @param  array<array{feature_key: string, value: string, type: string}>  $features
     */
    public function __construct(
        public string $planId,
        public array $prices,
        public array $features = [],
    ) {}
}
