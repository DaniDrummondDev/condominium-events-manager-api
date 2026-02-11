<?php

declare(strict_types=1);

use Application\Governance\Contracts\ViolationContestationRepositoryInterface;
use Application\Governance\Contracts\ViolationRepositoryInterface;
use Application\Governance\DTOs\ContestationDTO;
use Application\Governance\DTOs\ContestViolationDTO;
use Application\Governance\UseCases\ContestViolation;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Governance\Entities\Violation;
use Domain\Governance\Entities\ViolationContestation;
use Domain\Governance\Enums\ViolationSeverity;
use Domain\Governance\Enums\ViolationType;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createViolationForContest(): Violation
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
    $violation->pullDomainEvents();

    return $violation;
}

test('contests violation successfully and returns ContestationDTO with status pending', function () {
    $violation = createViolationForContest();

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('findById')->once()->andReturn($violation);
    $violationRepo->shouldReceive('save')->once();

    $contestationRepo = Mockery::mock(ViolationContestationRepositoryInterface::class);
    $contestationRepo->shouldReceive('findByViolation')->once()->andReturnNull();
    $contestationRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $tenantUserId = Uuid::generate()->value();
    $useCase = new ContestViolation($violationRepo, $contestationRepo, $eventDispatcher);

    $dto = new ContestViolationDTO(
        violationId: $violation->id()->value(),
        tenantUserId: $tenantUserId,
        reason: 'I did not commit this violation',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(ContestationDTO::class)
        ->and($result->status)->toBe('pending')
        ->and($result->reason)->toBe('I did not commit this violation')
        ->and($result->tenantUserId)->toBe($tenantUserId)
        ->and($result->violationId)->toBe($violation->id()->value())
        ->and($result->response)->toBeNull()
        ->and($result->respondedBy)->toBeNull()
        ->and($result->respondedAt)->toBeNull();
});

test('creates ViolationContestation and saves it', function () {
    $violation = createViolationForContest();

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('findById')->once()->andReturn($violation);
    $violationRepo->shouldReceive('save')->once();

    $contestationRepo = Mockery::mock(ViolationContestationRepositoryInterface::class);
    $contestationRepo->shouldReceive('findByViolation')->once()->andReturnNull();
    $contestationRepo->shouldReceive('save')
        ->once()
        ->withArgs(fn (ViolationContestation $c) => $c->reason() === 'I was not home');

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new ContestViolation($violationRepo, $contestationRepo, $eventDispatcher);

    $dto = new ContestViolationDTO(
        violationId: $violation->id()->value(),
        tenantUserId: Uuid::generate()->value(),
        reason: 'I was not home',
    );

    $useCase->execute($dto);
});

test('throws VIOLATION_NOT_FOUND when violation does not exist', function () {
    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('findById')->once()->andReturnNull();

    $contestationRepo = Mockery::mock(ViolationContestationRepositoryInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ContestViolation($violationRepo, $contestationRepo, $eventDispatcher);

    $dto = new ContestViolationDTO(
        violationId: Uuid::generate()->value(),
        tenantUserId: Uuid::generate()->value(),
        reason: 'Test reason',
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'Violation not found');

test('throws VIOLATION_ALREADY_CONTESTED when contestation already exists', function () {
    $violation = createViolationForContest();

    $existingContestation = ViolationContestation::create(
        id: Uuid::generate(),
        violationId: $violation->id(),
        tenantUserId: Uuid::generate(),
        reason: 'Previous contestation',
    );

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('findById')->once()->andReturn($violation);

    $contestationRepo = Mockery::mock(ViolationContestationRepositoryInterface::class);
    $contestationRepo->shouldReceive('findByViolation')->once()->andReturn($existingContestation);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ContestViolation($violationRepo, $contestationRepo, $eventDispatcher);

    $dto = new ContestViolationDTO(
        violationId: $violation->id()->value(),
        tenantUserId: Uuid::generate()->value(),
        reason: 'New contestation attempt',
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'already has a contestation');
