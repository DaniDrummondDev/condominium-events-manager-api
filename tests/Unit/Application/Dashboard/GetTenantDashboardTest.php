<?php

declare(strict_types=1);

use Application\Dashboard\Contracts\TenantDashboardQueryInterface;
use Application\Dashboard\DTOs\TenantDashboardDTO;
use Application\Dashboard\UseCases\GetTenantDashboard;

afterEach(fn () => Mockery::close());

function makeTenantDashboardDTO(): TenantDashboardDTO
{
    return new TenantDashboardDTO(
        totalUnits: 50,
        totalResidents: 120,
        totalSpaces: 5,
        reservationsThisMonth: 15,
        reservationsByStatus: ['confirmed' => 10, 'pending_approval' => 3, 'completed' => 2],
        pendingApprovals: 3,
        openViolations: 2,
        pendingReviewViolations: 1,
        activePenalties: 1,
        penaltiesThisMonth: 0,
        newResidentsThisMonth: 4,
        openSupportRequests: 6,
        unreadAnnouncementsAvg: 0.35,
    );
}

test('returns tenant dashboard DTO from query', function () {
    $expected = makeTenantDashboardDTO();

    $query = Mockery::mock(TenantDashboardQueryInterface::class);
    $query->shouldReceive('getAdminDashboard')->once()->andReturn($expected);

    $useCase = new GetTenantDashboard($query);
    $result = $useCase->execute();

    expect($result)->toBeInstanceOf(TenantDashboardDTO::class)
        ->and($result->totalUnits)->toBe(50)
        ->and($result->totalResidents)->toBe(120)
        ->and($result->totalSpaces)->toBe(5)
        ->and($result->reservationsThisMonth)->toBe(15)
        ->and($result->reservationsByStatus)->toBe(['confirmed' => 10, 'pending_approval' => 3, 'completed' => 2])
        ->and($result->pendingApprovals)->toBe(3)
        ->and($result->openViolations)->toBe(2)
        ->and($result->openSupportRequests)->toBe(6)
        ->and($result->unreadAnnouncementsAvg)->toBe(0.35);
});
