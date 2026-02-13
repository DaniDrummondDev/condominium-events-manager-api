<?php

declare(strict_types=1);

use Application\Dashboard\Contracts\TenantDashboardQueryInterface;
use Application\Dashboard\DTOs\ResidentDashboardDTO;
use Application\Dashboard\UseCases\GetResidentDashboard;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

test('returns resident dashboard DTO passing userId', function () {
    $userId = Uuid::generate()->value();

    $expected = new ResidentDashboardDTO(
        upcomingReservationsCount: 2,
        pastReservationsCount: 8,
        myViolationsCount: 1,
        myPenaltiesCount: 0,
        unreadAnnouncementsCount: 3,
        openSupportRequestsCount: 1,
    );

    $query = Mockery::mock(TenantDashboardQueryInterface::class);
    $query->shouldReceive('getResidentDashboard')
        ->once()
        ->with(Mockery::on(fn (Uuid $id) => $id->value() === $userId))
        ->andReturn($expected);

    $useCase = new GetResidentDashboard($query);
    $result = $useCase->execute($userId);

    expect($result)->toBeInstanceOf(ResidentDashboardDTO::class)
        ->and($result->upcomingReservationsCount)->toBe(2)
        ->and($result->pastReservationsCount)->toBe(8)
        ->and($result->myViolationsCount)->toBe(1)
        ->and($result->myPenaltiesCount)->toBe(0)
        ->and($result->unreadAnnouncementsCount)->toBe(3)
        ->and($result->openSupportRequestsCount)->toBe(1);
});
