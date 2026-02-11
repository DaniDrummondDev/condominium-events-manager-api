<?php

declare(strict_types=1);

use Application\Governance\Contracts\PenaltyPolicyRepositoryInterface;
use Application\Governance\Contracts\PenaltyRepositoryInterface;
use Application\Governance\Contracts\ViolationContestationRepositoryInterface;
use Application\Governance\Contracts\ViolationRepositoryInterface;
use Application\Governance\DTOs\ContestationDTO;
use Application\Governance\DTOs\ReviewContestationDTO;
use Application\Governance\UseCases\ApplyPenalty;
use Application\Governance\UseCases\EvaluatePenaltyPolicy;
use Application\Governance\UseCases\ReviewContestation;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Governance\Entities\Violation;
use Domain\Governance\Entities\ViolationContestation;
use Domain\Governance\Enums\ViolationSeverity;
use Domain\Governance\Enums\ViolationType;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createContestedViolationWithContestation(): array
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
    $violation->contest();
    $violation->pullDomainEvents();

    $contestation = ViolationContestation::create(
        id: Uuid::generate(),
        violationId: $violation->id(),
        tenantUserId: Uuid::generate(),
        reason: 'I did not commit this violation',
    );

    return [$violation, $contestation];
}

/**
 * Build a real EvaluatePenaltyPolicy instance with mocked inner dependencies.
 * EvaluatePenaltyPolicy and ApplyPenalty are final readonly classes and cannot be mocked.
 */
function buildRealEvaluatePenaltyPolicy(
    ?PenaltyPolicyRepositoryInterface $policyRepo = null,
    ?ViolationRepositoryInterface $innerViolationRepo = null,
): EvaluatePenaltyPolicy {
    $policyRepo ??= Mockery::mock(PenaltyPolicyRepositoryInterface::class);
    $innerViolationRepo ??= Mockery::mock(ViolationRepositoryInterface::class);
    $penaltyRepo = Mockery::mock(PenaltyRepositoryInterface::class);
    $innerEventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $applyPenalty = new ApplyPenalty($penaltyRepo, $innerEventDispatcher);

    return new EvaluatePenaltyPolicy($policyRepo, $innerViolationRepo, $applyPenalty);
}

test('accept: contestation accepted and violation revoked', function () {
    [$violation, $contestation] = createContestedViolationWithContestation();

    $contestationRepo = Mockery::mock(ViolationContestationRepositoryInterface::class);
    $contestationRepo->shouldReceive('findById')->once()->andReturn($contestation);
    $contestationRepo->shouldReceive('save')->once();

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('findById')->once()->andReturn($violation);
    $violationRepo->shouldReceive('save')->once();

    $evaluatePolicy = buildRealEvaluatePenaltyPolicy();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $respondedBy = Uuid::generate()->value();
    $useCase = new ReviewContestation($contestationRepo, $violationRepo, $evaluatePolicy, $eventDispatcher);

    $dto = new ReviewContestationDTO(
        contestationId: $contestation->id()->value(),
        respondedBy: $respondedBy,
        accepted: true,
        response: 'Contestation is valid, revoking violation',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(ContestationDTO::class)
        ->and($result->status)->toBe('accepted')
        ->and($result->response)->toBe('Contestation is valid, revoking violation')
        ->and($result->respondedBy)->toBe($respondedBy)
        ->and($result->respondedAt)->not->toBeNull();
});

test('reject: contestation rejected, violation upheld and evaluates penalty policy', function () {
    [$violation, $contestation] = createContestedViolationWithContestation();

    $contestationRepo = Mockery::mock(ViolationContestationRepositoryInterface::class);
    $contestationRepo->shouldReceive('findById')->once()->andReturn($contestation);
    $contestationRepo->shouldReceive('save')->once();

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('findById')->once()->andReturn($violation);
    $violationRepo->shouldReceive('save')->once();

    // Build real EvaluatePenaltyPolicy with mocked inner deps that confirm execution
    $policyRepo = Mockery::mock(PenaltyPolicyRepositoryInterface::class);
    $policyRepo->shouldReceive('findByViolationType')
        ->once()
        ->withArgs(fn ($type) => $type === ViolationType::NoShow)
        ->andReturn([]); // no matching policies, but confirms execute was called

    $evaluatePolicy = buildRealEvaluatePenaltyPolicy(policyRepo: $policyRepo);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new ReviewContestation($contestationRepo, $violationRepo, $evaluatePolicy, $eventDispatcher);

    $dto = new ReviewContestationDTO(
        contestationId: $contestation->id()->value(),
        respondedBy: Uuid::generate()->value(),
        accepted: false,
        response: 'Evidence confirms the violation',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(ContestationDTO::class)
        ->and($result->status)->toBe('rejected');
});

test('throws CONTESTATION_NOT_FOUND when contestation does not exist', function () {
    $contestationRepo = Mockery::mock(ViolationContestationRepositoryInterface::class);
    $contestationRepo->shouldReceive('findById')->once()->andReturnNull();

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $evaluatePolicy = buildRealEvaluatePenaltyPolicy();
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ReviewContestation($contestationRepo, $violationRepo, $evaluatePolicy, $eventDispatcher);

    $dto = new ReviewContestationDTO(
        contestationId: Uuid::generate()->value(),
        respondedBy: Uuid::generate()->value(),
        accepted: true,
        response: 'Test',
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'Contestation not found');

test('throws VIOLATION_NOT_FOUND when violation does not exist', function () {
    $contestation = ViolationContestation::create(
        id: Uuid::generate(),
        violationId: Uuid::generate(),
        tenantUserId: Uuid::generate(),
        reason: 'Test reason',
    );

    $contestationRepo = Mockery::mock(ViolationContestationRepositoryInterface::class);
    $contestationRepo->shouldReceive('findById')->once()->andReturn($contestation);

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('findById')->once()->andReturnNull();

    $evaluatePolicy = buildRealEvaluatePenaltyPolicy();
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ReviewContestation($contestationRepo, $violationRepo, $evaluatePolicy, $eventDispatcher);

    $dto = new ReviewContestationDTO(
        contestationId: $contestation->id()->value(),
        respondedBy: Uuid::generate()->value(),
        accepted: true,
        response: 'Test',
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'Violation not found');
