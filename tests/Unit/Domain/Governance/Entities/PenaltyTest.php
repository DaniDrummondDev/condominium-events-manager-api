<?php

declare(strict_types=1);

use Domain\Governance\Entities\Penalty;
use Domain\Governance\Enums\PenaltyStatus;
use Domain\Governance\Enums\PenaltyType;
use Domain\Governance\Events\PenaltyApplied;
use Domain\Governance\Events\PenaltyExpired;
use Domain\Governance\Events\PenaltyRevoked;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

function createPenalty(
    ?Uuid $id = null,
    ?Uuid $violationId = null,
    ?Uuid $unitId = null,
    PenaltyType $type = PenaltyType::TemporaryBlock,
    ?DateTimeImmutable $startsAt = null,
    ?DateTimeImmutable $endsAt = null,
    bool $withEndsAt = true,
): Penalty {
    return Penalty::create(
        id: $id ?? Uuid::generate(),
        violationId: $violationId ?? Uuid::generate(),
        unitId: $unitId ?? Uuid::generate(),
        type: $type,
        startsAt: $startsAt ?? new DateTimeImmutable,
        endsAt: $withEndsAt ? ($endsAt ?? new DateTimeImmutable('+7 days')) : null,
    );
}

// -- create() ----------------------------------------------------------------

test('create() sets status to Active', function () {
    $penalty = createPenalty();

    expect($penalty->status())->toBe(PenaltyStatus::Active);
});

test('create() emits PenaltyApplied event', function () {
    $penalty = createPenalty();
    $events = $penalty->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(PenaltyApplied::class);
});

test('create() PenaltyApplied has correct payload', function () {
    $id = Uuid::generate();
    $violationId = Uuid::generate();
    $unitId = Uuid::generate();
    $startsAt = new DateTimeImmutable('+1 day');
    $endsAt = new DateTimeImmutable('+8 days');

    $penalty = Penalty::create(
        id: $id,
        violationId: $violationId,
        unitId: $unitId,
        type: PenaltyType::TemporaryBlock,
        startsAt: $startsAt,
        endsAt: $endsAt,
    );
    $events = $penalty->pullDomainEvents();

    expect($events[0]->penaltyId)->toBe($id->value())
        ->and($events[0]->violationId)->toBe($violationId->value())
        ->and($events[0]->unitId)->toBe($unitId->value())
        ->and($events[0]->type)->toBe(PenaltyType::TemporaryBlock->value)
        ->and($events[0]->startsAt)->toBe($startsAt->format('c'))
        ->and($events[0]->endsAt)->toBe($endsAt->format('c'));
});

// -- revoke() ----------------------------------------------------------------

test('revoke() changes status to Revoked', function () {
    $penalty = createPenalty();
    $penalty->pullDomainEvents();

    $penalty->revoke(Uuid::generate(), 'Contestation accepted');

    expect($penalty->status())->toBe(PenaltyStatus::Revoked);
});

test('revoke() sets revokedBy, revokedAt, revokedReason', function () {
    $penalty = createPenalty();
    $revokedBy = Uuid::generate();

    $penalty->revoke($revokedBy, 'Administrative decision');

    expect($penalty->revokedBy())->toBe($revokedBy)
        ->and($penalty->revokedAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($penalty->revokedReason())->toBe('Administrative decision');
});

test('revoke() emits PenaltyRevoked event', function () {
    $penalty = createPenalty();
    $penalty->pullDomainEvents();
    $revokedBy = Uuid::generate();

    $penalty->revoke($revokedBy, 'Error in application');
    $events = $penalty->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(PenaltyRevoked::class)
        ->and($events[0]->penaltyId)->toBe($penalty->id()->value())
        ->and($events[0]->unitId)->toBe($penalty->unitId()->value())
        ->and($events[0]->revokedBy)->toBe($revokedBy->value())
        ->and($events[0]->reason)->toBe('Error in application');
});

test('revoke() on non-active penalty throws PENALTY_NOT_ACTIVE', function () {
    $penalty = createPenalty();
    $penalty->revoke(Uuid::generate(), 'First revoke');

    try {
        $penalty->revoke(Uuid::generate(), 'Second revoke');
        test()->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('PENALTY_NOT_ACTIVE')
            ->and($e->context())->toHaveKey('status', 'revoked');
    }
});

// -- expire() ----------------------------------------------------------------

test('expire() changes status to Expired', function () {
    $penalty = createPenalty();
    $penalty->pullDomainEvents();

    $penalty->expire();

    expect($penalty->status())->toBe(PenaltyStatus::Expired);
});

test('expire() emits PenaltyExpired event', function () {
    $penalty = createPenalty();
    $penalty->pullDomainEvents();

    $penalty->expire();
    $events = $penalty->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(PenaltyExpired::class)
        ->and($events[0]->penaltyId)->toBe($penalty->id()->value())
        ->and($events[0]->unitId)->toBe($penalty->unitId()->value());
});

test('expire() on non-active penalty throws PENALTY_NOT_ACTIVE', function () {
    $penalty = createPenalty();
    $penalty->expire();

    try {
        $penalty->expire();
        test()->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('PENALTY_NOT_ACTIVE')
            ->and($e->context())->toHaveKey('status', 'expired');
    }
});

// -- isActive() --------------------------------------------------------------

test('isActive() returns true when status is Active and endsAt is null', function () {
    $penalty = createPenalty(withEndsAt: false);

    expect($penalty->isActive())->toBeTrue();
});

test('isActive() returns true when status is Active and endsAt is in the future', function () {
    $penalty = createPenalty(endsAt: new DateTimeImmutable('+1 day'));

    expect($penalty->isActive())->toBeTrue();
});

test('isActive() returns false when status is Active but endsAt is in the past', function () {
    $penalty = createPenalty(endsAt: new DateTimeImmutable('-1 day'));

    expect($penalty->isActive())->toBeFalse();
});

test('isActive() returns false when status is Revoked', function () {
    $penalty = createPenalty();
    $penalty->revoke(Uuid::generate(), 'Revoked');

    expect($penalty->isActive())->toBeFalse();
});

// -- isBlocking() ------------------------------------------------------------

test('isBlocking() returns true when isActive and type is TemporaryBlock', function () {
    $penalty = createPenalty(type: PenaltyType::TemporaryBlock, endsAt: new DateTimeImmutable('+1 day'));

    expect($penalty->isBlocking())->toBeTrue();
});

test('isBlocking() returns false when type is Warning even if active', function () {
    $penalty = createPenalty(type: PenaltyType::Warning, endsAt: new DateTimeImmutable('+1 day'));

    expect($penalty->isBlocking())->toBeFalse();
});
