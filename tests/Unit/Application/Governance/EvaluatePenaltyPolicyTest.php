<?php

declare(strict_types=1);

use Application\Governance\Contracts\PenaltyPolicyRepositoryInterface;
use Application\Governance\Contracts\PenaltyRepositoryInterface;
use Application\Governance\Contracts\ViolationRepositoryInterface;
use Application\Governance\UseCases\ApplyPenalty;
use Application\Governance\UseCases\EvaluatePenaltyPolicy;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Governance\Entities\PenaltyPolicy;
use Domain\Governance\Enums\PenaltyType;
use Domain\Governance\Enums\ViolationType;
use Domain\Governance\Events\PenaltyApplied;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

test('threshold met calls ApplyPenalty', function () {
    $policy = PenaltyPolicy::create(
        id: Uuid::generate(),
        violationType: ViolationType::NoShow,
        occurrenceThreshold: 2,
        penaltyType: PenaltyType::TemporaryBlock,
        blockDays: 15,
    );

    $policyRepo = Mockery::mock(PenaltyPolicyRepositoryInterface::class);
    $policyRepo->shouldReceive('findByViolationType')->once()->andReturn([$policy]);

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('countByUnitAndType')->once()->andReturn(3); // exceeds threshold

    // ApplyPenalty is final readonly, so we inject a real instance with mocked dependencies
    $penaltyRepo = Mockery::mock(PenaltyRepositoryInterface::class);
    $penaltyRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof PenaltyApplied);

    $applyPenalty = new ApplyPenalty($penaltyRepo, $eventDispatcher);

    $violationId = Uuid::generate()->value();
    $unitId = Uuid::generate()->value();

    $useCase = new EvaluatePenaltyPolicy($policyRepo, $violationRepo, $applyPenalty);
    $useCase->execute($violationId, $unitId, 'no_show');
});

test('threshold not met does not call ApplyPenalty', function () {
    $policy = PenaltyPolicy::create(
        id: Uuid::generate(),
        violationType: ViolationType::NoShow,
        occurrenceThreshold: 5,
        penaltyType: PenaltyType::TemporaryBlock,
        blockDays: 15,
    );

    $policyRepo = Mockery::mock(PenaltyPolicyRepositoryInterface::class);
    $policyRepo->shouldReceive('findByViolationType')->once()->andReturn([$policy]);

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('countByUnitAndType')->once()->andReturn(2); // below threshold

    // ApplyPenalty is final readonly, so we inject a real instance with mocked dependencies
    // The penaltyRepo and eventDispatcher should NOT receive any calls
    $penaltyRepo = Mockery::mock(PenaltyRepositoryInterface::class);
    $penaltyRepo->shouldNotReceive('save');

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldNotReceive('dispatchAll');

    $applyPenalty = new ApplyPenalty($penaltyRepo, $eventDispatcher);

    $useCase = new EvaluatePenaltyPolicy($policyRepo, $violationRepo, $applyPenalty);
    $useCase->execute(Uuid::generate()->value(), Uuid::generate()->value(), 'no_show');
});

test('no matching policy does not call ApplyPenalty', function () {
    $policyRepo = Mockery::mock(PenaltyPolicyRepositoryInterface::class);
    $policyRepo->shouldReceive('findByViolationType')->once()->andReturn([]);

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);

    // ApplyPenalty is final readonly, so we inject a real instance with mocked dependencies
    $penaltyRepo = Mockery::mock(PenaltyRepositoryInterface::class);
    $penaltyRepo->shouldNotReceive('save');

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldNotReceive('dispatchAll');

    $applyPenalty = new ApplyPenalty($penaltyRepo, $eventDispatcher);

    $useCase = new EvaluatePenaltyPolicy($policyRepo, $violationRepo, $applyPenalty);
    $useCase->execute(Uuid::generate()->value(), Uuid::generate()->value(), 'no_show');
});
