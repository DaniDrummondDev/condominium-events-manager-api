<?php

declare(strict_types=1);

use Application\AI\ActionOrchestrator;
use Application\AI\Contracts\AIActionLogRepositoryInterface;
use Application\AI\DTOs\ActionLogDTO;
use Application\AI\ToolRegistry;
use Domain\Shared\Exceptions\DomainException;

afterEach(fn () => Mockery::close());

test('executes read-only tool calls directly', function () {
    $registry = new ToolRegistry();
    $registry->register(
        name: 'read_tool',
        description: 'Reads data',
        parameters: [],
        handler: fn () => 'read result',
    );

    $actionLogRepo = Mockery::mock(AIActionLogRepositoryInterface::class);

    $orchestrator = new ActionOrchestrator($registry, $actionLogRepo);

    $result = $orchestrator->processToolCalls(
        [['name' => 'read_tool', 'arguments' => []]],
        'user-123',
    );

    expect($result->readResults)->toBe(['read result'])
        ->and($result->proposedActions)->toBeEmpty();
});

test('creates action log for mutation tool calls', function () {
    $registry = new ToolRegistry();
    $registry->register(
        name: 'mutation_tool',
        description: 'Mutates data',
        parameters: [],
        handler: fn () => 'done',
        requiresConfirmation: true,
    );

    $actionLogRepo = Mockery::mock(AIActionLogRepositoryInterface::class);
    $actionLogRepo->shouldReceive('create')
        ->once()
        ->with('user-123', 'mutation_tool', ['param' => 'value'])
        ->andReturn('action-id-1');

    $orchestrator = new ActionOrchestrator($registry, $actionLogRepo);

    $result = $orchestrator->processToolCalls(
        [['name' => 'mutation_tool', 'arguments' => ['param' => 'value']]],
        'user-123',
    );

    expect($result->readResults)->toBeEmpty()
        ->and($result->proposedActions)->toHaveCount(1)
        ->and($result->proposedActions[0]->id)->toBe('action-id-1')
        ->and($result->proposedActions[0]->toolName)->toBe('mutation_tool')
        ->and($result->proposedActions[0]->requiresConfirmation)->toBeTrue();
});

test('confirms action and executes handler', function () {
    $registry = new ToolRegistry();
    $registry->register(
        name: 'mutation_tool',
        description: 'Mutates',
        parameters: [],
        handler: fn () => json_encode(['status' => 'created']),
        requiresConfirmation: true,
    );

    $actionLog = new ActionLogDTO(
        id: 'action-1',
        tenantUserId: 'user-1',
        toolName: 'mutation_tool',
        inputData: [],
        outputData: null,
        status: 'proposed',
        confirmedBy: null,
        executedAt: null,
        createdAt: '2026-01-01T00:00:00+00:00',
    );

    $actionLogRepo = Mockery::mock(AIActionLogRepositoryInterface::class);
    $actionLogRepo->shouldReceive('findById')->with('action-1')->andReturn($actionLog);
    $actionLogRepo->shouldReceive('updateStatus')->twice();

    $orchestrator = new ActionOrchestrator($registry, $actionLogRepo);

    $result = $orchestrator->confirmAction('action-1', 'confirmer-1');

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('result');
});

test('rejects action', function () {
    $actionLog = new ActionLogDTO(
        id: 'action-1',
        tenantUserId: 'user-1',
        toolName: 'some_tool',
        inputData: [],
        outputData: null,
        status: 'proposed',
        confirmedBy: null,
        executedAt: null,
        createdAt: '2026-01-01T00:00:00+00:00',
    );

    $actionLogRepo = Mockery::mock(AIActionLogRepositoryInterface::class);
    $actionLogRepo->shouldReceive('findById')->with('action-1')->andReturn($actionLog);
    $actionLogRepo->shouldReceive('updateStatus')
        ->once()
        ->with('action-1', 'rejected', Mockery::any(), Mockery::on(fn ($v) => $v === null || is_array($v)));

    $registry = new ToolRegistry();
    $orchestrator = new ActionOrchestrator($registry, $actionLogRepo);

    $orchestrator->rejectAction('action-1', 'Not needed');
});

test('throws when confirming non-existent action', function () {
    $actionLogRepo = Mockery::mock(AIActionLogRepositoryInterface::class);
    $actionLogRepo->shouldReceive('findById')->with('missing-id')->andReturn(null);

    $registry = new ToolRegistry();
    $orchestrator = new ActionOrchestrator($registry, $actionLogRepo);

    $orchestrator->confirmAction('missing-id', 'user-1');
})->throws(DomainException::class);
