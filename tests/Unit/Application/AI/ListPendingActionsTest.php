<?php

declare(strict_types=1);

use Application\AI\Contracts\AIActionLogRepositoryInterface;
use Application\AI\DTOs\ActionLogDTO;
use Application\AI\UseCases\ListPendingActions;

afterEach(fn () => Mockery::close());

test('returns pending actions for user', function () {
    $actions = [
        new ActionLogDTO(
            id: 'action-1',
            tenantUserId: 'user-1',
            toolName: 'create_reservation',
            inputData: ['space_id' => 'space-1'],
            outputData: null,
            status: 'proposed',
            confirmedBy: null,
            executedAt: null,
            createdAt: '2026-01-01T00:00:00+00:00',
        ),
    ];

    $repo = Mockery::mock(AIActionLogRepositoryInterface::class);
    $repo->shouldReceive('findPendingByUser')
        ->once()
        ->with('user-1')
        ->andReturn($actions);

    $useCase = new ListPendingActions($repo);

    $result = $useCase->execute('user-1');

    expect($result)->toHaveCount(1)
        ->and($result[0]->toolName)->toBe('create_reservation');
});

test('returns empty array when no pending actions', function () {
    $repo = Mockery::mock(AIActionLogRepositoryInterface::class);
    $repo->shouldReceive('findPendingByUser')
        ->once()
        ->with('user-1')
        ->andReturn([]);

    $useCase = new ListPendingActions($repo);

    expect($useCase->execute('user-1'))->toBeEmpty();
});
