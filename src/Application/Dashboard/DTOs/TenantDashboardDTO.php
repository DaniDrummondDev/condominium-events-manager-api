<?php

declare(strict_types=1);

namespace Application\Dashboard\DTOs;

final readonly class TenantDashboardDTO
{
    /**
     * @param array<string, int> $reservationsByStatus
     */
    public function __construct(
        public int $totalUnits,
        public int $totalResidents,
        public int $totalSpaces,
        public int $reservationsThisMonth,
        public array $reservationsByStatus,
        public int $pendingApprovals,
        public int $openViolations,
        public int $pendingReviewViolations,
        public int $activePenalties,
        public int $penaltiesThisMonth,
        public int $newResidentsThisMonth,
        public int $openSupportRequests,
        public float $unreadAnnouncementsAvg,
    ) {}
}
