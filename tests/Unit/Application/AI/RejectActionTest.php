<?php

declare(strict_types=1);

use Application\AI\ActionOrchestrator;
use Application\AI\UseCases\RejectAction;

afterEach(fn () => Mockery::close());

test('rejects action with reason', function () {
    $orchestrator = Mockery::mock(ActionOrchestrator::class);
    $orchestrator->shouldReceive('rejectAction')
        ->once()
        ->with('action-1', 'Não quero');

    $useCase = new RejectAction($orchestrator);

    $useCase->execute('action-1', 'Não quero');
});

test('rejects action without reason', function () {
    $orchestrator = Mockery::mock(ActionOrchestrator::class);
    $orchestrator->shouldReceive('rejectAction')
        ->once()
        ->with('action-1', null);

    $useCase = new RejectAction($orchestrator);

    $useCase->execute('action-1');
});
