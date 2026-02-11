<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class CreatePlanDTO
{
    /**
     * @param  array<array{feature_key: string, value: string, type: string}>  $features
     */
    public function __construct(
        public string $name,
        public string $slug,
        public int $priceInCents,
        public string $currency,
        public string $billingCycle,
        public int $trialDays,
        public array $features = [],
    ) {}
}
