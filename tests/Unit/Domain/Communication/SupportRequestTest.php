<?php

declare(strict_types=1);

use Domain\Communication\Entities\SupportRequest;
use Domain\Communication\Enums\ClosedReason;
use Domain\Communication\Enums\SupportRequestCategory;
use Domain\Communication\Enums\SupportRequestPriority;
use Domain\Communication\Enums\SupportRequestStatus;
use Domain\Communication\Events\SupportRequestClosed;
use Domain\Communication\Events\SupportRequestCreated;
use Domain\Communication\Events\SupportRequestResolved;
use Domain\Communication\Events\SupportRequestUpdated;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

function createSupportRequest(): SupportRequest
{
    return SupportRequest::create(
        id: Uuid::generate(),
        userId: Uuid::generate(),
        subject: 'Torneira vazando',
        category: SupportRequestCategory::Maintenance,
        priority: SupportRequestPriority::High,
    );
}

function createInProgressRequest(): SupportRequest
{
    $request = createSupportRequest();
    $request->pullDomainEvents();
    $request->startProgress();
    $request->pullDomainEvents();

    return $request;
}

test('create sets open status and emits event', function () {
    $request = createSupportRequest();

    expect($request->status())->toBe(SupportRequestStatus::Open);
    expect($request->subject())->toBe('Torneira vazando');
    expect($request->category())->toBe(SupportRequestCategory::Maintenance);
    expect($request->priority())->toBe(SupportRequestPriority::High);
    expect($request->closedAt())->toBeNull();
    expect($request->closedReason())->toBeNull();

    $events = $request->pullDomainEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(SupportRequestCreated::class);
});

test('start progress transitions from open to in_progress', function () {
    $request = createSupportRequest();
    $request->pullDomainEvents();

    $request->startProgress();

    expect($request->status())->toBe(SupportRequestStatus::InProgress);

    $events = $request->pullDomainEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(SupportRequestUpdated::class);
});

test('resolve transitions from in_progress to resolved', function () {
    $request = createInProgressRequest();

    $request->resolve();

    expect($request->status())->toBe(SupportRequestStatus::Resolved);

    $events = $request->pullDomainEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(SupportRequestResolved::class);
});

test('close transitions from open to closed', function () {
    $request = createSupportRequest();
    $request->pullDomainEvents();

    $request->close(ClosedReason::AdminClosed);

    expect($request->status())->toBe(SupportRequestStatus::Closed);
    expect($request->closedAt())->toBeInstanceOf(DateTimeImmutable::class);
    expect($request->closedReason())->toBe(ClosedReason::AdminClosed);

    $events = $request->pullDomainEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(SupportRequestClosed::class);
});

test('close transitions from in_progress to closed', function () {
    $request = createInProgressRequest();

    $request->close(ClosedReason::Resolved);

    expect($request->status())->toBe(SupportRequestStatus::Closed);
    expect($request->closedReason())->toBe(ClosedReason::Resolved);
});

test('close transitions from resolved to closed', function () {
    $request = createInProgressRequest();
    $request->resolve();
    $request->pullDomainEvents();

    $request->close(ClosedReason::Resolved);

    expect($request->status())->toBe(SupportRequestStatus::Closed);
});

test('reopen transitions from resolved to open', function () {
    $request = createInProgressRequest();
    $request->resolve();
    $request->pullDomainEvents();

    $request->reopen();

    expect($request->status())->toBe(SupportRequestStatus::Open);
    expect($request->closedAt())->toBeNull();
    expect($request->closedReason())->toBeNull();

    $events = $request->pullDomainEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(SupportRequestUpdated::class);
});

test('cannot resolve from open directly', function () {
    $request = createSupportRequest();
    $request->pullDomainEvents();

    expect(fn () => $request->resolve())->toThrow(
        DomainException::class,
        "Cannot transition support request from 'open' to 'resolved'",
    );
});

test('cannot reopen from open', function () {
    $request = createSupportRequest();
    $request->pullDomainEvents();

    expect(fn () => $request->reopen())->toThrow(
        DomainException::class,
        "Cannot transition support request from 'open' to 'open'",
    );
});

test('cannot transition from closed', function () {
    $request = createSupportRequest();
    $request->pullDomainEvents();
    $request->close(ClosedReason::AdminClosed);
    $request->pullDomainEvents();

    expect(fn () => $request->startProgress())->toThrow(DomainException::class);
    expect(fn () => $request->resolve())->toThrow(DomainException::class);
    expect(fn () => $request->reopen())->toThrow(DomainException::class);
});

test('pullDomainEvents clears events', function () {
    $request = createSupportRequest();

    $first = $request->pullDomainEvents();
    $second = $request->pullDomainEvents();

    expect($first)->toHaveCount(1);
    expect($second)->toHaveCount(0);
});
