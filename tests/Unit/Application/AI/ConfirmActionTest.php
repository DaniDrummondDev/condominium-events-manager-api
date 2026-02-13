<?php

declare(strict_types=1);

use Application\AI\ActionOrchestrator;
use Application\AI\Contracts\AIActionLogRepositoryInterface;
use Application\AI\DTOs\ActionLogDTO;
use Application\AI\UseCases\ConfirmAction;
use Domain\Shared\Exceptions\DomainException;

afterEach(fn () => Mockery::close());

test('confirms and executes action', function () {
    $actionLog = new ActionLogDTO(
        id: 'action-1',
        tenantUserId: 'user-1',
        toolName: 'create_reservation',
        inputData: ['space_id' => 'space-1'],
        outputData: null,
        status: 'proposed',
        confirmedBy: null,
        executedAt: null,
        createdAt: '2026-01-01T00:00:00+00:00',
    );

    $actionLogRepo = Mockery::mock(AIActionLogRepositoryInterface::class);
    $actionLogRepo->shouldReceive('findById')->with('action-1')->andReturn($actionLog);

    $orchestrator = Mockery::mock(ActionOrchestrator::class);
    $orchestrator->shouldReceive('confirmAction')
        ->once()
        ->with('action-1', 'confirmer-1')
        ->andReturn(['status' => 'created']);

    $useCase = new ConfirmAction($actionLogRepo, $orchestrator);

    $result = $useCase->execute('action-1', 'confirmer-1');

    expect($result)->toBe(['status' => 'created']);
});

test('throws when action not found', function () {
    $actionLogRepo = Mockery::mock(AIActionLogRepositoryInterface::class);
    $actionLogRepo->shouldReceive('findById')->with('missing')->andReturn(null);

    $orchestrator = Mockery::mock(ActionOrchestrator::class);

    $useCase = new ConfirmAction($actionLogRepo, $orchestrator);

    $useCase->execute('missing', 'user-1');
})->throws(DomainException::class);
