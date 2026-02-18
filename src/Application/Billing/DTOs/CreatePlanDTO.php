<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class CreatePlanDTO
{
    /**
     * @param  array<array{billing_cycle: string, price_in_cents: int, currency: string, trial_days: int}>  $prices
     * @param  array<array{feature_key: string, value: string, type: string}>  $features
     */
    public function __construct(
        public string $name,
        public string $slug,
        public array $prices,
        public array $features = [],
    ) {}
}
