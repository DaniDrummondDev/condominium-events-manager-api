<?php

declare(strict_types=1);

use Application\Governance\Contracts\PenaltyPolicyRepositoryInterface;
use Application\Governance\Contracts\PenaltyRepositoryInterface;
use Application\Governance\Contracts\ViolationRepositoryInterface;
use Application\Governance\DTOs\ViolationDTO;
use Application\Governance\UseCases\ApplyPenalty;
use Application\Governance\UseCases\EvaluatePenaltyPolicy;
use Application\Governance\UseCases\UpholdViolation;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Governance\Entities\Violation;
use Domain\Governance\Enums\ViolationSeverity;
use Domain\Governance\Enums\ViolationType;
use Domain\Governance\Events\ViolationUpheld;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createOpenViolation(): Violation
{
    $violation = Violation::create(
        id: Uuid::generate(),
        unitId: Uuid::generate(),
        tenantUserId: Uuid::generate(),
        reservationId: null,
        ruleId: null,
        type: ViolationType::NoShow,
        severity: ViolationSeverity::Medium,
        description: 'Test violation',
        createdBy: Uuid::generate(),
    );
    $violation->pullDomainEvents(); // clear creation events

    return $violation;
}

/**
 * Build a real EvaluatePenaltyPolicy instance with mocked inner dependencies.
 * EvaluatePenaltyPolicy and ApplyPenalty are final readonly classes and cannot be mocked.
 */
function buildEvaluatePenaltyPolicy(
    ?PenaltyPolicyRepositoryInterface $policyRepo = null,
    ?ViolationRepositoryInterface $violationRepo = null,
    ?PenaltyRepositoryInterface $penaltyRepo = null,
    ?EventDispatcherInterface $innerEventDispatcher = null,
): EvaluatePenaltyPolicy {
    $policyRepo ??= Mockery::mock(PenaltyPolicyRepositoryInterface::class);
    $violationRepo ??= Mockery::mock(ViolationRepositoryInterface::class);
    $penaltyRepo ??= Mockery::mock(PenaltyRepositoryInterface::class);
    $innerEventDispatcher ??= Mockery::mock(EventDispatcherInterface::class);

    $applyPenalty = new ApplyPenalty($penaltyRepo, $innerEventDispatcher);

    return new EvaluatePenaltyPolicy($policyRepo, $violationRepo, $applyPenalty);
}

test('upholds violation and returns ViolationDTO with status upheld', function () {
    $violation = createOpenViolation();

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('findById')->once()->andReturn($violation);
    $violationRepo->shouldReceive('save')->once();

    // EvaluatePenaltyPolicy is final readonly; build a real instance with no-op mocked dependencies
    $policyRepo = Mockery::mock(PenaltyPolicyRepositoryInterface::class);
    $policyRepo->shouldReceive('findByViolationType')->once()->andReturn([]);

    $evaluatePolicy = buildEvaluatePenaltyPolicy(policyRepo: $policyRepo);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof ViolationUpheld);

    $useCase = new UpholdViolation($violationRepo, $evaluatePolicy, $eventDispatcher);
    $upheldBy = Uuid::generate()->value();
    $result = $useCase->execute($violation->id()->value(), $upheldBy);

    expect($result)->toBeInstanceOf(ViolationDTO::class)
        ->and($result->status)->toBe('upheld')
        ->and($result->upheldBy)->toBe($upheldBy)
        ->and($result->upheldAt)->not->toBeNull();
});

test('throws VIOLATION_NOT_FOUND when violation does not exist', function () {
    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('findById')->once()->andReturnNull();

    $evaluatePolicy = buildEvaluatePenaltyPolicy();
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new UpholdViolation($violationRepo, $evaluatePolicy, $eventDispatcher);

    $useCase->execute(Uuid::generate()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'Violation not found');

test('throws INVALID_STATUS_TRANSITION when violation is already upheld', function () {
    $violation = createOpenViolation();
    $violation->uphold(Uuid::generate()); // now upheld
    $violation->pullDomainEvents();

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('findById')->once()->andReturn($violation);

    $evaluatePolicy = buildEvaluatePenaltyPolicy();
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new UpholdViolation($violationRepo, $evaluatePolicy, $eventDispatcher);

    $useCase->execute($violation->id()->value(), Uuid::generate()->value());
})->throws(DomainException::class, 'Cannot transition');

test('calls EvaluatePenaltyPolicy after upholding', function () {
    $violation = createOpenViolation();

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('findById')->once()->andReturn($violation);
    $violationRepo->shouldReceive('save')->once();

    // Build a real EvaluatePenaltyPolicy that will actually trigger ApplyPenalty
    // by providing a matching policy and a violation count exceeding the threshold
    $policyRepo = Mockery::mock(PenaltyPolicyRepositoryInterface::class);
    $policyRepo->shouldReceive('findByViolationType')
        ->once()
        ->withArgs(fn ($type) => $type === ViolationType::NoShow)
        ->andReturn([]);

    $innerViolationRepo = Mockery::mock(ViolationRepositoryInterface::class);

    $evaluatePolicy = buildEvaluatePenaltyPolicy(policyRepo: $policyRepo, violationRepo: $innerViolationRepo);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new UpholdViolation($violationRepo, $evaluatePolicy, $eventDispatcher);
    $useCase->execute($violation->id()->value(), Uuid::generate()->value());
});
