<?php

declare(strict_types=1);

namespace Application\Dashboard\DTOs;

final readonly class PlatformDashboardDTO
{
    /**
     * @param array<string, int> $subscriptionsByStatus
     */
    public function __construct(
        public int $totalActiveTenants,
        public int $newTenantsThisMonth,
        public int $totalSubscriptions,
        public array $subscriptionsByStatus,
        public int $trialSubscriptions,
        public int $pastDueSubscriptions,
        public int $mrrCents,
        public ?int $totalReservationsPlatform,
    ) {}
}
