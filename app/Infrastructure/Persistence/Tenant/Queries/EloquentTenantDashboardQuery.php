<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Queries;

use App\Infrastructure\Persistence\Tenant\Models\AnnouncementModel;
use App\Infrastructure\Persistence\Tenant\Models\AnnouncementReadModel;
use App\Infrastructure\Persistence\Tenant\Models\PenaltyModel;
use App\Infrastructure\Persistence\Tenant\Models\ResidentModel;
use App\Infrastructure\Persistence\Tenant\Models\ReservationModel;
use App\Infrastructure\Persistence\Tenant\Models\SpaceModel;
use App\Infrastructure\Persistence\Tenant\Models\SupportRequestModel;
use App\Infrastructure\Persistence\Tenant\Models\UnitModel;
use App\Infrastructure\Persistence\Tenant\Models\ViolationModel;
use Application\Dashboard\Contracts\TenantDashboardQueryInterface;
use Application\Dashboard\DTOs\ResidentDashboardDTO;
use Application\Dashboard\DTOs\TenantDashboardDTO;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Support\Carbon;

class EloquentTenantDashboardQuery implements TenantDashboardQueryInterface
{
    public function getAdminDashboard(): TenantDashboardDTO
    {
        $now = Carbon::now();

        $totalUnits = UnitModel::where('status', 'active')->count();
        $totalResidents = ResidentModel::where('status', 'active')->count();
        $totalSpaces = SpaceModel::where('status', 'active')->count();

        $reservationsThisMonth = ReservationModel::whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $reservationsByStatus = ReservationModel::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $pendingApprovals = ReservationModel::where('status', 'pending_approval')->count();

        $openViolations = ViolationModel::where('status', 'registered')->count();
        $pendingReviewViolations = ViolationModel::where('status', 'under_review')->count();

        $activePenalties = PenaltyModel::where('status', 'active')->count();
        $penaltiesThisMonth = PenaltyModel::whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $newResidentsThisMonth = ResidentModel::whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $openSupportRequests = SupportRequestModel::whereIn('status', ['open', 'in_progress'])->count();

        $publishedAnnouncements = AnnouncementModel::where('status', 'published')->count();
        $totalReads = AnnouncementReadModel::count();
        $unreadAnnouncementsAvg = $publishedAnnouncements > 0
            ? round(1 - ($totalReads / ($publishedAnnouncements * max($totalResidents, 1))), 2)
            : 0.0;

        return new TenantDashboardDTO(
            totalUnits: $totalUnits,
            totalResidents: $totalResidents,
            totalSpaces: $totalSpaces,
            reservationsThisMonth: $reservationsThisMonth,
            reservationsByStatus: $reservationsByStatus,
            pendingApprovals: $pendingApprovals,
            openViolations: $openViolations,
            pendingReviewViolations: $pendingReviewViolations,
            activePenalties: $activePenalties,
            penaltiesThisMonth: $penaltiesThisMonth,
            newResidentsThisMonth: $newResidentsThisMonth,
            openSupportRequests: $openSupportRequests,
            unreadAnnouncementsAvg: $unreadAnnouncementsAvg,
        );
    }

    public function getResidentDashboard(Uuid $userId): ResidentDashboardDTO
    {
        $now = Carbon::now();

        $resident = ResidentModel::where('tenant_user_id', $userId->value())->first();
        $unitId = $resident?->unit_id;

        $upcomingReservationsCount = $unitId
            ? ReservationModel::where('unit_id', $unitId)
                ->where('start_datetime', '>=', $now)
                ->whereNotIn('status', ['canceled', 'rejected'])
                ->count()
            : 0;

        $pastReservationsCount = $unitId
            ? ReservationModel::where('unit_id', $unitId)
                ->where('start_datetime', '<', $now)
                ->count()
            : 0;

        $myViolationsCount = $unitId
            ? ViolationModel::where('unit_id', $unitId)->count()
            : 0;

        $myPenaltiesCount = $unitId
            ? PenaltyModel::where('unit_id', $unitId)->count()
            : 0;

        $publishedAnnouncementIds = AnnouncementModel::where('status', 'published')
            ->pluck('id');

        $readAnnouncementIds = AnnouncementReadModel::where('tenant_user_id', $userId->value())
            ->pluck('announcement_id');

        $unreadAnnouncementsCount = $publishedAnnouncementIds->diff($readAnnouncementIds)->count();

        $openSupportRequestsCount = SupportRequestModel::where('tenant_user_id', $userId->value())
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        return new ResidentDashboardDTO(
            upcomingReservationsCount: $upcomingReservationsCount,
            pastReservationsCount: $pastReservationsCount,
            myViolationsCount: $myViolationsCount,
            myPenaltiesCount: $myPenaltiesCount,
            unreadAnnouncementsCount: $unreadAnnouncementsCount,
            openSupportRequestsCount: $openSupportRequestsCount,
        );
    }
}
