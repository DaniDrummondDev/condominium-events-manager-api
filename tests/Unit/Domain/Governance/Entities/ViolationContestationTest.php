<?php

declare(strict_types=1);

use Domain\Governance\Entities\ViolationContestation;
use Domain\Governance\Enums\ContestationStatus;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

function createContestation(
    ?Uuid $id = null,
    ?Uuid $violationId = null,
    ?Uuid $tenantUserId = null,
    string $reason = 'I was not present at the time',
): ViolationContestation {
    return ViolationContestation::create(
        id: $id ?? Uuid::generate(),
        violationId: $violationId ?? Uuid::generate(),
        tenantUserId: $tenantUserId ?? Uuid::generate(),
        reason: $reason,
    );
}

// -- create() ----------------------------------------------------------------

test('create() sets status to Pending', function () {
    $contestation = createContestation();

    expect($contestation->status())->toBe(ContestationStatus::Pending);
});

test('create() sets all properties correctly', function () {
    $id = Uuid::generate();
    $violationId = Uuid::generate();
    $tenantUserId = Uuid::generate();

    $contestation = ViolationContestation::create(
        id: $id,
        violationId: $violationId,
        tenantUserId: $tenantUserId,
        reason: 'Wrong identification',
    );

    expect($contestation->id())->toBe($id)
        ->and($contestation->violationId())->toBe($violationId)
        ->and($contestation->tenantUserId())->toBe($tenantUserId)
        ->and($contestation->reason())->toBe('Wrong identification')
        ->and($contestation->response())->toBeNull()
        ->and($contestation->respondedBy())->toBeNull()
        ->and($contestation->respondedAt())->toBeNull()
        ->and($contestation->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
});

// -- accept() ----------------------------------------------------------------

test('accept() changes status to Accepted', function () {
    $contestation = createContestation();
    $respondedBy = Uuid::generate();

    $contestation->accept($respondedBy, 'Evidence verified');

    expect($contestation->status())->toBe(ContestationStatus::Accepted);
});

test('accept() sets respondedBy, response, respondedAt', function () {
    $contestation = createContestation();
    $respondedBy = Uuid::generate();

    $contestation->accept($respondedBy, 'Contestation is valid');

    expect($contestation->respondedBy())->toBe($respondedBy)
        ->and($contestation->response())->toBe('Contestation is valid')
        ->and($contestation->respondedAt())->toBeInstanceOf(DateTimeImmutable::class);
});

// -- reject() ----------------------------------------------------------------

test('reject() changes status to Rejected', function () {
    $contestation = createContestation();
    $respondedBy = Uuid::generate();

    $contestation->reject($respondedBy, 'Evidence insufficient');

    expect($contestation->status())->toBe(ContestationStatus::Rejected);
});

test('reject() sets respondedBy, response, respondedAt', function () {
    $contestation = createContestation();
    $respondedBy = Uuid::generate();

    $contestation->reject($respondedBy, 'No supporting evidence');

    expect($contestation->respondedBy())->toBe($respondedBy)
        ->and($contestation->response())->toBe('No supporting evidence')
        ->and($contestation->respondedAt())->toBeInstanceOf(DateTimeImmutable::class);
});

// -- already reviewed --------------------------------------------------------

test('accept() after already accepted throws CONTESTATION_ALREADY_REVIEWED', function () {
    $contestation = createContestation();
    $contestation->accept(Uuid::generate(), 'First response');

    try {
        $contestation->accept(Uuid::generate(), 'Second response');
        test()->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('CONTESTATION_ALREADY_REVIEWED')
            ->and($e->context())->toHaveKey('status', 'accepted');
    }
});

test('reject() after already rejected throws CONTESTATION_ALREADY_REVIEWED', function () {
    $contestation = createContestation();
    $contestation->reject(Uuid::generate(), 'First rejection');

    try {
        $contestation->reject(Uuid::generate(), 'Second rejection');
        test()->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('CONTESTATION_ALREADY_REVIEWED')
            ->and($e->context())->toHaveKey('status', 'rejected');
    }
});
