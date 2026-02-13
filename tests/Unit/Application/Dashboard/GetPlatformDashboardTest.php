<?php

declare(strict_types=1);

use Application\Dashboard\Contracts\PlatformDashboardQueryInterface;
use Application\Dashboard\DTOs\PlatformDashboardDTO;
use Application\Dashboard\UseCases\GetPlatformDashboard;

afterEach(fn () => Mockery::close());

test('returns platform dashboard DTO from query', function () {
    $expected = new PlatformDashboardDTO(
        totalActiveTenants: 25,
        newTenantsThisMonth: 3,
        totalSubscriptions: 30,
        subscriptionsByStatus: ['active' => 20, 'trial' => 5, 'past_due' => 3, 'canceled' => 2],
        trialSubscriptions: 5,
        pastDueSubscriptions: 3,
        mrrCents: 250000,
        totalReservationsPlatform: null,
    );

    $query = Mockery::mock(PlatformDashboardQueryInterface::class);
    $query->shouldReceive('getPlatformDashboard')->once()->andReturn($expected);

    $useCase = new GetPlatformDashboard($query);
    $result = $useCase->execute();

    expect($result)->toBeInstanceOf(PlatformDashboardDTO::class)
        ->and($result->totalActiveTenants)->toBe(25)
        ->and($result->newTenantsThisMonth)->toBe(3)
        ->and($result->totalSubscriptions)->toBe(30)
        ->and($result->subscriptionsByStatus)->toHaveCount(4)
        ->and($result->trialSubscriptions)->toBe(5)
        ->and($result->pastDueSubscriptions)->toBe(3)
        ->and($result->mrrCents)->toBe(250000)
        ->and($result->totalReservationsPlatform)->toBeNull();
});
