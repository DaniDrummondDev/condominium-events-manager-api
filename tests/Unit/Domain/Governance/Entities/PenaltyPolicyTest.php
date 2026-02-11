<?php

declare(strict_types=1);

use Domain\Governance\Entities\PenaltyPolicy;
use Domain\Governance\Enums\PenaltyType;
use Domain\Governance\Enums\ViolationType;
use Domain\Shared\ValueObjects\Uuid;

function createPenaltyPolicy(
    ?Uuid $id = null,
    ViolationType $violationType = ViolationType::NoShow,
    int $occurrenceThreshold = 3,
    PenaltyType $penaltyType = PenaltyType::TemporaryBlock,
    ?int $blockDays = 7,
): PenaltyPolicy {
    return PenaltyPolicy::create(
        id: $id ?? Uuid::generate(),
        violationType: $violationType,
        occurrenceThreshold: $occurrenceThreshold,
        penaltyType: $penaltyType,
        blockDays: $blockDays,
    );
}

// -- create() ----------------------------------------------------------------

test('create() sets all properties correctly', function () {
    $id = Uuid::generate();

    $policy = PenaltyPolicy::create(
        id: $id,
        violationType: ViolationType::NoShow,
        occurrenceThreshold: 3,
        penaltyType: PenaltyType::TemporaryBlock,
        blockDays: 7,
    );

    expect($policy->id())->toBe($id)
        ->and($policy->violationType())->toBe(ViolationType::NoShow)
        ->and($policy->occurrenceThreshold())->toBe(3)
        ->and($policy->penaltyType())->toBe(PenaltyType::TemporaryBlock)
        ->and($policy->blockDays())->toBe(7)
        ->and($policy->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('create() defaults isActive to true', function () {
    $policy = createPenaltyPolicy();

    expect($policy->isActive())->toBeTrue();
});

// -- update() ----------------------------------------------------------------

test('update() changes occurrenceThreshold, penaltyType, blockDays', function () {
    $policy = createPenaltyPolicy(
        occurrenceThreshold: 3,
        penaltyType: PenaltyType::TemporaryBlock,
        blockDays: 7,
    );

    $policy->update(
        occurrenceThreshold: 5,
        penaltyType: PenaltyType::PermanentBlock,
        blockDays: null,
    );

    expect($policy->occurrenceThreshold())->toBe(5)
        ->and($policy->penaltyType())->toBe(PenaltyType::PermanentBlock)
        ->and($policy->blockDays())->toBeNull();
});

// -- matches() ---------------------------------------------------------------

test('matches() returns true for matching ViolationType when active', function () {
    $policy = createPenaltyPolicy(violationType: ViolationType::NoShow);

    expect($policy->matches(ViolationType::NoShow))->toBeTrue();
});

test('matches() returns false for non-matching ViolationType', function () {
    $policy = createPenaltyPolicy(violationType: ViolationType::NoShow);

    expect($policy->matches(ViolationType::Damage))->toBeFalse();
});

test('matches() returns false when inactive even if type matches', function () {
    $policy = createPenaltyPolicy(violationType: ViolationType::NoShow);
    $policy->deactivate();

    expect($policy->matches(ViolationType::NoShow))->toBeFalse();
});

// -- activate() / deactivate() -----------------------------------------------

test('activate() sets isActive to true', function () {
    $policy = createPenaltyPolicy();
    $policy->deactivate();

    $policy->activate();

    expect($policy->isActive())->toBeTrue();
});

test('deactivate() sets isActive to false', function () {
    $policy = createPenaltyPolicy();

    $policy->deactivate();

    expect($policy->isActive())->toBeFalse();
});
