<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class CreateSubscriptionDTO
{
    public function __construct(
        public string $tenantId,
        public string $planVersionId,
        public string $billingCycle,
        public ?string $startDate = null,
    ) {}
}
