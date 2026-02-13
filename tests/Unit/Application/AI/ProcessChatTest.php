<?php

declare(strict_types=1);

use Application\AI\ActionOrchestrator;
use Application\AI\Contracts\AIUsageLogRepositoryInterface;
use Application\AI\Contracts\TextGenerationInterface;
use Application\AI\DTOs\ChatRequestDTO;
use Application\AI\DTOs\OrchestratorResult;
use Application\AI\DTOs\TextGenerationResult;
use Application\AI\SessionManager;
use Application\AI\SystemPromptBuilder;
use Application\AI\ToolRegistry;
use Application\AI\UseCases\ProcessChat;

afterEach(fn () => Mockery::close());

test('processes chat message and returns response', function () {
    $textGen = Mockery::mock(TextGenerationInterface::class);
    $textGen->shouldReceive('chat')
        ->once()
        ->andReturn(new TextGenerationResult(
            text: 'Olá! Como posso ajudar?',
            toolCalls: [],
            tokensInput: 100,
            tokensOutput: 50,
            finishReason: 'stop',
        ));

    $registry = new ToolRegistry();

    $orchestrator = Mockery::mock(ActionOrchestrator::class);

    $sessionManager = Mockery::mock(SessionManager::class);
    $sessionManager->shouldReceive('getOrCreateSession')->once()->andReturn('session-1');
    $sessionManager->shouldReceive('addMessage')->twice();
    $sessionManager->shouldReceive('getMessages')->once()->andReturn([
        ['role' => 'user', 'content' => 'Olá'],
    ]);

    $promptBuilder = Mockery::mock(SystemPromptBuilder::class);
    $promptBuilder->shouldReceive('build')->once()->andReturn('System prompt');

    $usageLog = Mockery::mock(AIUsageLogRepositoryInterface::class);
    $usageLog->shouldReceive('log')->once();

    config(['ai.allowed_roles' => ['sindico', 'administradora', 'condomino']]);

    $useCase = new ProcessChat($textGen, $registry, $orchestrator, $sessionManager, $promptBuilder, $usageLog);

    $result = $useCase->execute(
        dto: new ChatRequestDTO(message: 'Olá'),
        tenantUserId: 'user-1',
        tenantName: 'Condo Test',
        userName: 'João',
        userRole: 'condomino',
    );

    expect($result->response)->toBe('Olá! Como posso ajudar?')
        ->and($result->sessionId)->toBe('session-1')
        ->and($result->suggestedActions)->toBeEmpty();
});

test('processes chat with tool calls', function () {
    $firstResponse = new TextGenerationResult(
        text: '',
        toolCalls: [['name' => 'read_tool', 'arguments' => []]],
        tokensInput: 100,
        tokensOutput: 20,
        finishReason: 'tool_calls',
    );

    $secondResponse = new TextGenerationResult(
        text: 'Aqui estão os dados solicitados.',
        toolCalls: [],
        tokensInput: 200,
        tokensOutput: 50,
        finishReason: 'stop',
    );

    $textGen = Mockery::mock(TextGenerationInterface::class);
    $textGen->shouldReceive('chat')
        ->twice()
        ->andReturn($firstResponse, $secondResponse);

    $registry = new ToolRegistry();

    $orchestrator = Mockery::mock(ActionOrchestrator::class);
    $orchestrator->shouldReceive('processToolCalls')
        ->once()
        ->andReturn(new OrchestratorResult(readResults: ['some data'], proposedActions: []));

    $sessionManager = Mockery::mock(SessionManager::class);
    $sessionManager->shouldReceive('getOrCreateSession')->andReturn('session-2');
    $sessionManager->shouldReceive('addMessage');
    $sessionManager->shouldReceive('getMessages')->andReturn([
        ['role' => 'user', 'content' => 'Mostre dados'],
    ]);

    $promptBuilder = Mockery::mock(SystemPromptBuilder::class);
    $promptBuilder->shouldReceive('build')->andReturn('System prompt');

    $usageLog = Mockery::mock(AIUsageLogRepositoryInterface::class);
    $usageLog->shouldReceive('log')->once();

    config(['ai.allowed_roles' => ['sindico', 'administradora', 'condomino']]);

    $useCase = new ProcessChat($textGen, $registry, $orchestrator, $sessionManager, $promptBuilder, $usageLog);

    $result = $useCase->execute(
        dto: new ChatRequestDTO(message: 'Mostre dados'),
        tenantUserId: 'user-1',
        tenantName: 'Condo Test',
        userName: 'João',
        userRole: 'sindico',
    );

    expect($result->response)->toBe('Aqui estão os dados solicitados.');
});

test('denies access for unauthorized role', function () {
    $textGen = Mockery::mock(TextGenerationInterface::class);
    $registry = new ToolRegistry();
    $orchestrator = Mockery::mock(ActionOrchestrator::class);
    $sessionManager = Mockery::mock(SessionManager::class);
    $promptBuilder = Mockery::mock(SystemPromptBuilder::class);
    $usageLog = Mockery::mock(AIUsageLogRepositoryInterface::class);

    config(['ai.allowed_roles' => ['sindico', 'administradora', 'condomino']]);

    $useCase = new ProcessChat($textGen, $registry, $orchestrator, $sessionManager, $promptBuilder, $usageLog);

    $useCase->execute(
        dto: new ChatRequestDTO(message: 'Olá'),
        tenantUserId: 'user-1',
        tenantName: 'Condo',
        userName: 'Func',
        userRole: 'funcionario',
    );
})->throws(\Domain\Shared\Exceptions\DomainException::class);
