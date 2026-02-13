<?php

declare(strict_types=1);

use Domain\Communication\Enums\SupportRequestStatus;

test('has correct cases', function () {
    expect(SupportRequestStatus::cases())->toHaveCount(4);
    expect(SupportRequestStatus::Open->value)->toBe('open');
    expect(SupportRequestStatus::InProgress->value)->toBe('in_progress');
    expect(SupportRequestStatus::Resolved->value)->toBe('resolved');
    expect(SupportRequestStatus::Closed->value)->toBe('closed');
});

test('open can transition to in_progress', function () {
    expect(SupportRequestStatus::Open->canTransitionTo(SupportRequestStatus::InProgress))->toBeTrue();
});

test('open can transition to closed', function () {
    expect(SupportRequestStatus::Open->canTransitionTo(SupportRequestStatus::Closed))->toBeTrue();
});

test('in_progress can transition to resolved', function () {
    expect(SupportRequestStatus::InProgress->canTransitionTo(SupportRequestStatus::Resolved))->toBeTrue();
});

test('in_progress can transition to closed', function () {
    expect(SupportRequestStatus::InProgress->canTransitionTo(SupportRequestStatus::Closed))->toBeTrue();
});

test('resolved can transition to open (reopen)', function () {
    expect(SupportRequestStatus::Resolved->canTransitionTo(SupportRequestStatus::Open))->toBeTrue();
});

test('resolved can transition to closed', function () {
    expect(SupportRequestStatus::Resolved->canTransitionTo(SupportRequestStatus::Closed))->toBeTrue();
});

test('closed cannot transition to any state', function () {
    expect(SupportRequestStatus::Closed->canTransitionTo(SupportRequestStatus::Open))->toBeFalse();
    expect(SupportRequestStatus::Closed->canTransitionTo(SupportRequestStatus::InProgress))->toBeFalse();
    expect(SupportRequestStatus::Closed->canTransitionTo(SupportRequestStatus::Resolved))->toBeFalse();
});

test('open cannot transition to resolved directly', function () {
    expect(SupportRequestStatus::Open->canTransitionTo(SupportRequestStatus::Resolved))->toBeFalse();
});

test('closed is terminal', function () {
    expect(SupportRequestStatus::Closed->isTerminal())->toBeTrue();
});

test('non-closed states are not terminal', function () {
    expect(SupportRequestStatus::Open->isTerminal())->toBeFalse();
    expect(SupportRequestStatus::InProgress->isTerminal())->toBeFalse();
    expect(SupportRequestStatus::Resolved->isTerminal())->toBeFalse();
});

test('open and in_progress are active', function () {
    expect(SupportRequestStatus::Open->isActive())->toBeTrue();
    expect(SupportRequestStatus::InProgress->isActive())->toBeTrue();
});

test('resolved and closed are not active', function () {
    expect(SupportRequestStatus::Resolved->isActive())->toBeFalse();
    expect(SupportRequestStatus::Closed->isActive())->toBeFalse();
});

test('labels are correct', function () {
    expect(SupportRequestStatus::Open->label())->toBe('Aberta');
    expect(SupportRequestStatus::InProgress->label())->toBe('Em Andamento');
    expect(SupportRequestStatus::Resolved->label())->toBe('Resolvida');
    expect(SupportRequestStatus::Closed->label())->toBe('Fechada');
});
