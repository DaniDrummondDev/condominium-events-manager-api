<?php

declare(strict_types=1);

use Application\Governance\Contracts\ViolationRepositoryInterface;
use Application\Governance\DTOs\RegisterViolationDTO;
use Application\Governance\DTOs\ViolationDTO;
use Application\Governance\UseCases\RegisterViolation;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Governance\Events\ViolationRegistered;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

test('registers manual violation successfully and returns ViolationDTO with status open', function () {
    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(fn (array $events) => count($events) === 1 && $events[0] instanceof ViolationRegistered);

    $useCase = new RegisterViolation($violationRepo, $eventDispatcher);

    $dto = new RegisterViolationDTO(
        unitId: Uuid::generate()->value(),
        tenantUserId: Uuid::generate()->value(),
        reservationId: null,
        ruleId: null,
        type: 'no_show',
        severity: 'medium',
        description: 'Did not show up for reservation',
        createdBy: Uuid::generate()->value(),
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(ViolationDTO::class)
        ->and($result->status)->toBe('open')
        ->and($result->type)->toBe('no_show')
        ->and($result->severity)->toBe('medium')
        ->and($result->description)->toBe('Did not show up for reservation')
        ->and($result->isAutomatic)->toBeFalse();
});

test('dispatches ViolationRegistered event', function () {
    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(function (array $events) {
            return count($events) === 1
                && $events[0] instanceof ViolationRegistered
                && $events[0]->type === 'noise_complaint'
                && $events[0]->severity === 'high'
                && $events[0]->isAutomatic === false;
        });

    $useCase = new RegisterViolation($violationRepo, $eventDispatcher);

    $dto = new RegisterViolationDTO(
        unitId: Uuid::generate()->value(),
        tenantUserId: Uuid::generate()->value(),
        reservationId: null,
        ruleId: Uuid::generate()->value(),
        type: 'noise_complaint',
        severity: 'high',
        description: 'Loud party after 10pm',
        createdBy: Uuid::generate()->value(),
    );

    $useCase->execute($dto);
});

test('returns ViolationDTO with all nullable fields correctly mapped', function () {
    $unitId = Uuid::generate()->value();
    $tenantUserId = Uuid::generate()->value();
    $reservationId = Uuid::generate()->value();
    $ruleId = Uuid::generate()->value();
    $createdBy = Uuid::generate()->value();

    $violationRepo = Mockery::mock(ViolationRepositoryInterface::class);
    $violationRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new RegisterViolation($violationRepo, $eventDispatcher);

    $dto = new RegisterViolationDTO(
        unitId: $unitId,
        tenantUserId: $tenantUserId,
        reservationId: $reservationId,
        ruleId: $ruleId,
        type: 'damage',
        severity: 'critical',
        description: 'Broken window',
        createdBy: $createdBy,
    );

    $result = $useCase->execute($dto);

    expect($result->unitId)->toBe($unitId)
        ->and($result->tenantUserId)->toBe($tenantUserId)
        ->and($result->reservationId)->toBe($reservationId)
        ->and($result->ruleId)->toBe($ruleId)
        ->and($result->createdBy)->toBe($createdBy)
        ->and($result->upheldBy)->toBeNull()
        ->and($result->upheldAt)->toBeNull()
        ->and($result->revokedBy)->toBeNull()
        ->and($result->revokedAt)->toBeNull()
        ->and($result->revokedReason)->toBeNull();
});
