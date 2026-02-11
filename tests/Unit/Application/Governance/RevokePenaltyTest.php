<?php

declare(strict_types=1);

use Application\Governance\Contracts\PenaltyRepositoryInterface;
use Application\Governance\DTOs\PenaltyDTO;
use Application\Governance\DTOs\RevokePenaltyDTO;
use Application\Governance\UseCases\RevokePenalty;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Governance\Entities\Penalty;
use Domain\Governance\Enums\PenaltyType;
use Domain\Governance\Events\PenaltyRevoked;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createActivePenalty(): Penalty
{
    $penalty = Penalty::create(
        id: Uuid::generate(),
        violationId: Uuid::generate(),
        unitId: Uuid::generate(),
        type: PenaltyType::TemporaryBlock,
        startsAt: new DateTimeImmutable,
        endsAt: new DateTimeImmutable('+15 days'),
    );
    $penalty->pullDomainEvents();

    return $penalty;
}

test('revokes penalty and returns PenaltyDTO with status revoked', function () {
    $penalty = createActivePenalty();

    $penaltyRepo = Mockery::mock(PenaltyRepositoryInterface::class);
    $penaltyRepo->shouldReceive('findById')->once()->andReturn($penalty);
    $penaltyRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof PenaltyRevoked);

    $revokedBy = Uuid::generate()->value();
    $useCase = new RevokePenalty($penaltyRepo, $eventDispatcher);

    $dto = new RevokePenaltyDTO(
        penaltyId: $penalty->id()->value(),
        revokedBy: $revokedBy,
        reason: 'Penalty applied in error',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(PenaltyDTO::class)
        ->and($result->status)->toBe('revoked')
        ->and($result->revokedBy)->toBe($revokedBy)
        ->and($result->revokedReason)->toBe('Penalty applied in error')
        ->and($result->revokedAt)->not->toBeNull();
});

test('throws PENALTY_NOT_FOUND when penalty does not exist', function () {
    $penaltyRepo = Mockery::mock(PenaltyRepositoryInterface::class);
    $penaltyRepo->shouldReceive('findById')->once()->andReturnNull();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new RevokePenalty($penaltyRepo, $eventDispatcher);

    $dto = new RevokePenaltyDTO(
        penaltyId: Uuid::generate()->value(),
        revokedBy: Uuid::generate()->value(),
        reason: 'Test reason',
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'Penalty not found');

test('throws PENALTY_NOT_ACTIVE when penalty is already revoked', function () {
    $penalty = createActivePenalty();
    $penalty->revoke(Uuid::generate(), 'Already revoked');
    $penalty->pullDomainEvents();

    $penaltyRepo = Mockery::mock(PenaltyRepositoryInterface::class);
    $penaltyRepo->shouldReceive('findById')->once()->andReturn($penalty);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new RevokePenalty($penaltyRepo, $eventDispatcher);

    $dto = new RevokePenaltyDTO(
        penaltyId: $penalty->id()->value(),
        revokedBy: Uuid::generate()->value(),
        reason: 'Trying again',
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'Cannot revoke penalty with status');
