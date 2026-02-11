<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class CancelSubscriptionDTO
{
    public function __construct(
        public string $subscriptionId,
        public string $cancellationType = 'end_of_period',
    ) {}
}
