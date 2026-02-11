<?php

declare(strict_types=1);

use Domain\Governance\Entities\Violation;
use Domain\Governance\Enums\ViolationSeverity;
use Domain\Governance\Enums\ViolationStatus;
use Domain\Governance\Enums\ViolationType;
use Domain\Governance\Events\ViolationContested;
use Domain\Governance\Events\ViolationRegistered;
use Domain\Governance\Events\ViolationRevoked;
use Domain\Governance\Events\ViolationUpheld;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

function createViolation(
    ?Uuid $id = null,
    ?Uuid $unitId = null,
    ?Uuid $tenantUserId = null,
    ?Uuid $reservationId = null,
    ?Uuid $ruleId = null,
    ViolationType $type = ViolationType::NoShow,
    ViolationSeverity $severity = ViolationSeverity::Medium,
    string $description = 'Test violation',
    ?Uuid $createdBy = null,
): Violation {
    return Violation::create(
        id: $id ?? Uuid::generate(),
        unitId: $unitId ?? Uuid::generate(),
        tenantUserId: $tenantUserId ?? Uuid::generate(),
        reservationId: $reservationId,
        ruleId: $ruleId,
        type: $type,
        severity: $severity,
        description: $description,
        createdBy: $createdBy ?? Uuid::generate(),
    );
}

function createContestedViolation(): Violation
{
    $violation = createViolation();
    $violation->contest();
    $violation->pullDomainEvents();

    return $violation;
}

// -- create() ----------------------------------------------------------------

test('create() sets status to Open', function () {
    $violation = createViolation();

    expect($violation->status())->toBe(ViolationStatus::Open);
});

test('create() sets isAutomatic to false', function () {
    $violation = createViolation();

    expect($violation->isAutomatic())->toBeFalse();
});

test('create() sets createdBy', function () {
    $createdBy = Uuid::generate();

    $violation = createViolation(createdBy: $createdBy);

    expect($violation->createdBy())->toBe($createdBy);
});

test('create() emits ViolationRegistered event', function () {
    $violation = createViolation();
    $events = $violation->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ViolationRegistered::class);
});

test('create() ViolationRegistered has correct payload', function () {
    $id = Uuid::generate();
    $unitId = Uuid::generate();

    $violation = createViolation(
        id: $id,
        unitId: $unitId,
        type: ViolationType::NoShow,
        severity: ViolationSeverity::Medium,
    );
    $events = $violation->pullDomainEvents();

    expect($events[0]->violationId)->toBe($id->value())
        ->and($events[0]->unitId)->toBe($unitId->value())
        ->and($events[0]->type)->toBe(ViolationType::NoShow->value)
        ->and($events[0]->severity)->toBe(ViolationSeverity::Medium->value)
        ->and($events[0]->isAutomatic)->toBeFalse();
});

// -- createAutomatic() -------------------------------------------------------

test('createAutomatic() sets isAutomatic to true', function () {
    $violation = Violation::createAutomatic(
        id: Uuid::generate(),
        unitId: Uuid::generate(),
        tenantUserId: Uuid::generate(),
        reservationId: Uuid::generate(),
        type: ViolationType::NoShow,
        severity: ViolationSeverity::Medium,
        description: 'Automatic violation',
    );

    expect($violation->isAutomatic())->toBeTrue();
});

test('createAutomatic() sets createdBy to null', function () {
    $violation = Violation::createAutomatic(
        id: Uuid::generate(),
        unitId: Uuid::generate(),
        tenantUserId: Uuid::generate(),
        reservationId: null,
        type: ViolationType::LateCancellation,
        severity: ViolationSeverity::Low,
        description: 'Auto violation',
    );

    expect($violation->createdBy())->toBeNull();
});

test('createAutomatic() emits ViolationRegistered with isAutomatic=true', function () {
    $violation = Violation::createAutomatic(
        id: Uuid::generate(),
        unitId: Uuid::generate(),
        tenantUserId: Uuid::generate(),
        reservationId: null,
        type: ViolationType::NoShow,
        severity: ViolationSeverity::High,
        description: 'Auto violation',
    );
    $events = $violation->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ViolationRegistered::class)
        ->and($events[0]->isAutomatic)->toBeTrue();
});

// -- uphold() ----------------------------------------------------------------

test('uphold() changes status to Upheld', function () {
    $violation = createViolation();
    $violation->pullDomainEvents();

    $violation->uphold(Uuid::generate());

    expect($violation->status())->toBe(ViolationStatus::Upheld);
});

test('uphold() sets upheldBy and upheldAt', function () {
    $violation = createViolation();
    $upheldBy = Uuid::generate();

    $violation->uphold($upheldBy);

    expect($violation->upheldBy())->toBe($upheldBy)
        ->and($violation->upheldAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('uphold() emits ViolationUpheld event', function () {
    $violation = createViolation();
    $violation->pullDomainEvents();
    $upheldBy = Uuid::generate();

    $violation->uphold($upheldBy);
    $events = $violation->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ViolationUpheld::class)
        ->and($events[0]->violationId)->toBe($violation->id()->value())
        ->and($events[0]->upheldBy)->toBe($upheldBy->value());
});

test('uphold() from Contested status works', function () {
    $violation = createContestedViolation();

    $violation->uphold(Uuid::generate());

    expect($violation->status())->toBe(ViolationStatus::Upheld);
});

// -- revoke() ----------------------------------------------------------------

test('revoke() changes status to Revoked', function () {
    $violation = createViolation();
    $violation->pullDomainEvents();

    $violation->revoke(Uuid::generate(), 'Insufficient evidence');

    expect($violation->status())->toBe(ViolationStatus::Revoked);
});

test('revoke() sets revokedBy, revokedAt, revokedReason', function () {
    $violation = createViolation();
    $revokedBy = Uuid::generate();

    $violation->revoke($revokedBy, 'Error in report');

    expect($violation->revokedBy())->toBe($revokedBy)
        ->and($violation->revokedAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($violation->revokedReason())->toBe('Error in report');
});

test('revoke() emits ViolationRevoked event', function () {
    $violation = createViolation();
    $violation->pullDomainEvents();
    $revokedBy = Uuid::generate();

    $violation->revoke($revokedBy, 'Wrong person');
    $events = $violation->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ViolationRevoked::class)
        ->and($events[0]->violationId)->toBe($violation->id()->value())
        ->and($events[0]->revokedBy)->toBe($revokedBy->value())
        ->and($events[0]->reason)->toBe('Wrong person');
});

test('revoke() from Contested status works', function () {
    $violation = createContestedViolation();

    $violation->revoke(Uuid::generate(), 'Contestation accepted');

    expect($violation->status())->toBe(ViolationStatus::Revoked);
});

// -- contest() ---------------------------------------------------------------

test('contest() changes status to Contested', function () {
    $violation = createViolation();
    $violation->pullDomainEvents();

    $violation->contest();

    expect($violation->status())->toBe(ViolationStatus::Contested);
});

test('contest() emits ViolationContested event', function () {
    $violation = createViolation();
    $violation->pullDomainEvents();

    $violation->contest();
    $events = $violation->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(ViolationContested::class)
        ->and($events[0]->violationId)->toBe($violation->id()->value())
        ->and($events[0]->unitId)->toBe($violation->unitId()->value());
});

test('contest() from Upheld throws DomainException (INVALID_STATUS_TRANSITION)', function () {
    $violation = createViolation();
    $violation->uphold(Uuid::generate());

    try {
        $violation->contest();
        test()->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVALID_STATUS_TRANSITION')
            ->and($e->context())->toHaveKey('current_status', 'upheld')
            ->and($e->context())->toHaveKey('new_status', 'contested');
    }
});

test('contest() from Revoked throws DomainException', function () {
    $violation = createViolation();
    $violation->revoke(Uuid::generate(), 'Reason');

    try {
        $violation->contest();
        test()->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVALID_STATUS_TRANSITION')
            ->and($e->context())->toHaveKey('current_status', 'revoked')
            ->and($e->context())->toHaveKey('new_status', 'contested');
    }
});

// -- uphold() from invalid states --------------------------------------------

test('uphold() from Revoked throws DomainException', function () {
    $violation = createViolation();
    $violation->revoke(Uuid::generate(), 'Reason');

    try {
        $violation->uphold(Uuid::generate());
        test()->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVALID_STATUS_TRANSITION')
            ->and($e->context())->toHaveKey('current_status', 'revoked')
            ->and($e->context())->toHaveKey('new_status', 'upheld');
    }
});

// -- isActive() --------------------------------------------------------------

test('isActive() returns true for Open and Contested, false for Upheld and Revoked', function () {
    $openViolation = createViolation();
    expect($openViolation->isActive())->toBeTrue();

    $contestedViolation = createContestedViolation();
    expect($contestedViolation->isActive())->toBeTrue();

    $upheldViolation = createViolation();
    $upheldViolation->uphold(Uuid::generate());
    expect($upheldViolation->isActive())->toBeFalse();

    $revokedViolation = createViolation();
    $revokedViolation->revoke(Uuid::generate(), 'Reason');
    expect($revokedViolation->isActive())->toBeFalse();
});
