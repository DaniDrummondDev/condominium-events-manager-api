<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class SubscriptionDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $planVersionId,
        public string $status,
        public string $billingCycle,
        public string $currentPeriodStart,
        public string $currentPeriodEnd,
        public ?string $gracePeriodEnd = null,
        public ?string $canceledAt = null,
    ) {}
}
