<?php

declare(strict_types=1);

use Domain\Billing\Entities\DunningPolicy;
use Domain\Shared\ValueObjects\Uuid;

function createDunningPolicy(
    int $maxRetries = 3,
    array $retryIntervals = [3, 5, 7],
    int $suspendAfterDays = 15,
    int $cancelAfterDays = 30,
    bool $isDefault = true,
): DunningPolicy {
    return new DunningPolicy(
        id: Uuid::generate(),
        name: 'Default Dunning',
        maxRetries: $maxRetries,
        retryIntervals: $retryIntervals,
        suspendAfterDays: $suspendAfterDays,
        cancelAfterDays: $cancelAfterDays,
        isDefault: $isDefault,
    );
}

// --- Basic accessors ---

test('creates dunning policy with correct attributes', function () {
    $id = Uuid::generate();
    $policy = new DunningPolicy(
        id: $id,
        name: 'Aggressive',
        maxRetries: 5,
        retryIntervals: [1, 2, 3, 5, 7],
        suspendAfterDays: 10,
        cancelAfterDays: 20,
        isDefault: false,
    );

    expect($policy->id())->toBe($id)
        ->and($policy->name())->toBe('Aggressive')
        ->and($policy->maxRetries())->toBe(5)
        ->and($policy->retryIntervals())->toBe([1, 2, 3, 5, 7])
        ->and($policy->suspendAfterDays())->toBe(10)
        ->and($policy->cancelAfterDays())->toBe(20)
        ->and($policy->isDefault())->toBeFalse();
});

test('isDefault returns true for default policy', function () {
    $policy = createDunningPolicy(isDefault: true);

    expect($policy->isDefault())->toBeTrue();
});

test('isDefault returns false for non-default policy', function () {
    $policy = createDunningPolicy(isDefault: false);

    expect($policy->isDefault())->toBeFalse();
});

// --- retryIntervalForAttempt ---

describe('retryIntervalForAttempt', function () {
    test('returns correct interval for first attempt', function () {
        $policy = createDunningPolicy(maxRetries: 3, retryIntervals: [3, 5, 7]);

        expect($policy->retryIntervalForAttempt(1))->toBe(3);
    });

    test('returns correct interval for second attempt', function () {
        $policy = createDunningPolicy(maxRetries: 3, retryIntervals: [3, 5, 7]);

        expect($policy->retryIntervalForAttempt(2))->toBe(5);
    });

    test('returns correct interval for last attempt', function () {
        $policy = createDunningPolicy(maxRetries: 3, retryIntervals: [3, 5, 7]);

        expect($policy->retryIntervalForAttempt(3))->toBe(7);
    });

    test('returns null for attempt zero', function () {
        $policy = createDunningPolicy(maxRetries: 3, retryIntervals: [3, 5, 7]);

        expect($policy->retryIntervalForAttempt(0))->toBeNull();
    });

    test('returns null for negative attempt', function () {
        $policy = createDunningPolicy(maxRetries: 3, retryIntervals: [3, 5, 7]);

        expect($policy->retryIntervalForAttempt(-1))->toBeNull();
    });

    test('returns null for attempt exceeding max retries', function () {
        $policy = createDunningPolicy(maxRetries: 3, retryIntervals: [3, 5, 7]);

        expect($policy->retryIntervalForAttempt(4))->toBeNull();
    });

    test('returns null when retry intervals array is shorter than max retries', function () {
        $policy = createDunningPolicy(maxRetries: 5, retryIntervals: [3, 5]);

        expect($policy->retryIntervalForAttempt(1))->toBe(3)
            ->and($policy->retryIntervalForAttempt(2))->toBe(5)
            ->and($policy->retryIntervalForAttempt(3))->toBeNull()
            ->and($policy->retryIntervalForAttempt(4))->toBeNull()
            ->and($policy->retryIntervalForAttempt(5))->toBeNull();
    });
});

// --- shouldSuspend ---

describe('shouldSuspend', function () {
    test('returns true when days past due equals suspend threshold', function () {
        $policy = createDunningPolicy(suspendAfterDays: 15);

        expect($policy->shouldSuspend(15))->toBeTrue();
    });

    test('returns true when days past due exceeds suspend threshold', function () {
        $policy = createDunningPolicy(suspendAfterDays: 15);

        expect($policy->shouldSuspend(20))->toBeTrue();
    });

    test('returns false when days past due is below suspend threshold', function () {
        $policy = createDunningPolicy(suspendAfterDays: 15);

        expect($policy->shouldSuspend(14))->toBeFalse();
    });

    test('returns false when days past due is zero', function () {
        $policy = createDunningPolicy(suspendAfterDays: 15);

        expect($policy->shouldSuspend(0))->toBeFalse();
    });
});

// --- shouldCancel ---

describe('shouldCancel', function () {
    test('returns true when days past due equals cancel threshold', function () {
        $policy = createDunningPolicy(cancelAfterDays: 30);

        expect($policy->shouldCancel(30))->toBeTrue();
    });

    test('returns true when days past due exceeds cancel threshold', function () {
        $policy = createDunningPolicy(cancelAfterDays: 30);

        expect($policy->shouldCancel(45))->toBeTrue();
    });

    test('returns false when days past due is below cancel threshold', function () {
        $policy = createDunningPolicy(cancelAfterDays: 30);

        expect($policy->shouldCancel(29))->toBeFalse();
    });

    test('returns false when days past due is zero', function () {
        $policy = createDunningPolicy(cancelAfterDays: 30);

        expect($policy->shouldCancel(0))->toBeFalse();
    });
});

// --- Combined scenarios ---

test('shouldSuspend triggers before shouldCancel', function () {
    $policy = createDunningPolicy(suspendAfterDays: 15, cancelAfterDays: 30);

    // At day 15: should suspend but not cancel
    expect($policy->shouldSuspend(15))->toBeTrue()
        ->and($policy->shouldCancel(15))->toBeFalse();

    // At day 30: should suspend AND cancel
    expect($policy->shouldSuspend(30))->toBeTrue()
        ->and($policy->shouldCancel(30))->toBeTrue();
});
