<?php

declare(strict_types=1);

use Application\Governance\Contracts\PenaltyRepositoryInterface;
use Application\Governance\DTOs\PenaltyDTO;
use Application\Governance\UseCases\ApplyPenalty;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Governance\Events\PenaltyApplied;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

test('applies penalty successfully and returns PenaltyDTO with status active', function () {
    $penaltyRepo = Mockery::mock(PenaltyRepositoryInterface::class);
    $penaltyRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof PenaltyApplied);

    $violationId = Uuid::generate()->value();
    $unitId = Uuid::generate()->value();

    $useCase = new ApplyPenalty($penaltyRepo, $eventDispatcher);
    $result = $useCase->execute(
        violationId: $violationId,
        unitId: $unitId,
        penaltyType: 'temporary_block',
        blockDays: 15,
    );

    expect($result)->toBeInstanceOf(PenaltyDTO::class)
        ->and($result->status)->toBe('active')
        ->and($result->type)->toBe('temporary_block')
        ->and($result->violationId)->toBe($violationId)
        ->and($result->unitId)->toBe($unitId);
});

test('dispatches PenaltyApplied event', function () {
    $penaltyRepo = Mockery::mock(PenaltyRepositoryInterface::class);
    $penaltyRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(function (array $events) {
            return count($events) === 1
                && $events[0] instanceof PenaltyApplied
                && $events[0]->type === 'warning';
        });

    $useCase = new ApplyPenalty($penaltyRepo, $eventDispatcher);
    $useCase->execute(
        violationId: Uuid::generate()->value(),
        unitId: Uuid::generate()->value(),
        penaltyType: 'warning',
        blockDays: null,
    );
});

test('applies penalty with blockDays and endsAt is set', function () {
    $penaltyRepo = Mockery::mock(PenaltyRepositoryInterface::class);
    $penaltyRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new ApplyPenalty($penaltyRepo, $eventDispatcher);
    $result = $useCase->execute(
        violationId: Uuid::generate()->value(),
        unitId: Uuid::generate()->value(),
        penaltyType: 'temporary_block',
        blockDays: 30,
    );

    expect($result->endsAt)->not->toBeNull()
        ->and($result->startsAt)->not->toBeNull();
});
