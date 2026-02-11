<?php

declare(strict_types=1);

use Application\Governance\Contracts\ViolationRepositoryInterface;
use Application\Governance\DTOs\ViolationDTO;
use Application\Governance\UseCases\RevokeViolation;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Governance\Entities\Violation;
use Domain\Governance\Enums\ViolationSeverity;
use Domain\Governance\Enums\ViolationType;
use Domain\Governance\Events\ViolationRevoked;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createViolationForRevoke(): Violation
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

test('revokes violation and returns ViolationDTO with status revoked and revokedReason', function () {
    $violation = createViolationForRevoke();

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('findById')->once()->andReturn($violation);
    $violationRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof ViolationRevoked);

    $revokedBy = Uuid::generate()->value();
    $useCase = new RevokeViolation($violationRepo, $eventDispatcher);
    $result = $useCase->execute($violation->id()->value(), $revokedBy, 'False alarm');

    expect($result)->toBeInstanceOf(ViolationDTO::class)
        ->and($result->status)->toBe('revoked')
        ->and($result->revokedBy)->toBe($revokedBy)
        ->and($result->revokedReason)->toBe('False alarm')
        ->and($result->revokedAt)->not->toBeNull();
});

test('throws VIOLATION_NOT_FOUND when violation does not exist', function () {
    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('findById')->once()->andReturnNull();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new RevokeViolation($violationRepo, $eventDispatcher);

    $useCase->execute(Uuid::generate()->value(), Uuid::generate()->value(), 'reason');
})->throws(DomainException::class, 'Violation not found');

test('throws INVALID_STATUS_TRANSITION when violation is already revoked', function () {
    $violation = createViolationForRevoke();
    $violation->revoke(Uuid::generate(), 'Already revoked');
    $violation->pullDomainEvents();

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('findById')->once()->andReturn($violation);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new RevokeViolation($violationRepo, $eventDispatcher);

    $useCase->execute($violation->id()->value(), Uuid::generate()->value(), 'reason');
})->throws(DomainException::class, 'Cannot transition');
