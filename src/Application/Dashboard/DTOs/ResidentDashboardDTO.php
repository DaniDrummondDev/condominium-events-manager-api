<?php

declare(strict_types=1);

namespace Application\Dashboard\DTOs;

final readonly class ResidentDashboardDTO
{
    public function __construct(
        public int $upcomingReservationsCount,
        public int $pastReservationsCount,
        public int $myViolationsCount,
        public int $myPenaltiesCount,
        public int $unreadAnnouncementsCount,
        public int $openSupportRequestsCount,
    ) {}
}
