<?php

declare(strict_types=1);

use Domain\Billing\Entities\Subscription;
use Domain\Billing\Enums\BillingCycle;
use Domain\Billing\Enums\SubscriptionStatus;
use Domain\Billing\Events\SubscriptionActivated;
use Domain\Billing\Events\SubscriptionCanceled;
use Domain\Billing\Events\SubscriptionExpired;
use Domain\Billing\Events\SubscriptionPlanChanged;
use Domain\Billing\Events\SubscriptionRenewed;
use Domain\Billing\Events\SubscriptionSuspended;
use Domain\Billing\ValueObjects\BillingPeriod;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

function createSubscription(SubscriptionStatus $status = SubscriptionStatus::Active): Subscription
{
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-02-01');
    $period = new BillingPeriod($start, $end);

    return new Subscription(
        id: Uuid::generate(),
        tenantId: Uuid::generate(),
        planVersionId: Uuid::generate(),
        status: $status,
        billingCycle: BillingCycle::Monthly,
        currentPeriod: $period,
    );
}

function createSubscriptionPeriod(string $start = '2025-01-01', string $end = '2025-02-01'): BillingPeriod
{
    return new BillingPeriod(
        new DateTimeImmutable($start),
        new DateTimeImmutable($end),
    );
}

// --- Factory methods ---

describe('create', function () {
    test('creates active subscription with SubscriptionActivated event', function () {
        $id = Uuid::generate();
        $tenantId = Uuid::generate();
        $planVersionId = Uuid::generate();
        $period = createSubscriptionPeriod();

        $subscription = Subscription::create($id, $tenantId, $planVersionId, BillingCycle::Monthly, $period);

        expect($subscription->id())->toBe($id)
            ->and($subscription->tenantId())->toBe($tenantId)
            ->and($subscription->planVersionId())->toBe($planVersionId)
            ->and($subscription->status())->toBe(SubscriptionStatus::Active)
            ->and($subscription->billingCycle())->toBe(BillingCycle::Monthly)
            ->and($subscription->currentPeriod())->toBe($period)
            ->and($subscription->gracePeriodEnd())->toBeNull()
            ->and($subscription->canceledAt())->toBeNull();

        $events = $subscription->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(SubscriptionActivated::class);
    });

    test('createTrialing creates trialing subscription without events', function () {
        $id = Uuid::generate();
        $tenantId = Uuid::generate();
        $planVersionId = Uuid::generate();
        $period = createSubscriptionPeriod();

        $subscription = Subscription::createTrialing($id, $tenantId, $planVersionId, BillingCycle::Yearly, $period);

        expect($subscription->status())->toBe(SubscriptionStatus::Trialing)
            ->and($subscription->billingCycle())->toBe(BillingCycle::Yearly);

        $events = $subscription->pullDomainEvents();
        expect($events)->toBeEmpty();
    });
});

// --- Valid transitions ---

describe('activate', function () {
    test('activates from Trialing', function () {
        $subscription = createSubscription(SubscriptionStatus::Trialing);

        $subscription->activate();

        expect($subscription->status())->toBe(SubscriptionStatus::Active);
    });

    test('emits SubscriptionActivated event', function () {
        $subscription = createSubscription(SubscriptionStatus::Trialing);

        $subscription->activate();

        $events = $subscription->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(SubscriptionActivated::class);
    });

    test('cannot activate from Active', function () {
        $subscription = createSubscription(SubscriptionStatus::Active);

        $subscription->activate();
    })->throws(DomainException::class);

    test('cannot activate from Expired', function () {
        $subscription = createSubscription(SubscriptionStatus::Expired);

        $subscription->activate();
    })->throws(DomainException::class);

    test('cannot activate from Canceled', function () {
        $subscription = createSubscription(SubscriptionStatus::Canceled);

        $subscription->activate();
    })->throws(DomainException::class);
});

describe('markPastDue', function () {
    test('marks Active subscription as past due', function () {
        $subscription = createSubscription(SubscriptionStatus::Active);

        $subscription->markPastDue();

        expect($subscription->status())->toBe(SubscriptionStatus::PastDue);
    });

    test('cannot mark Trialing as past due', function () {
        $subscription = createSubscription(SubscriptionStatus::Trialing);

        $subscription->markPastDue();
    })->throws(DomainException::class);
});

describe('enterGracePeriod', function () {
    test('enters grace period from PastDue', function () {
        $subscription = createSubscription(SubscriptionStatus::PastDue);
        $graceEnd = new DateTimeImmutable('2025-02-15');

        $subscription->enterGracePeriod($graceEnd);

        expect($subscription->status())->toBe(SubscriptionStatus::GracePeriod)
            ->and($subscription->gracePeriodEnd())->toBe($graceEnd);
    });

    test('cannot enter grace period from Active', function () {
        $subscription = createSubscription(SubscriptionStatus::Active);
        $graceEnd = new DateTimeImmutable('2025-02-15');

        $subscription->enterGracePeriod($graceEnd);
    })->throws(DomainException::class);
});

describe('suspend', function () {
    test('suspends from GracePeriod', function () {
        $subscription = createSubscription(SubscriptionStatus::GracePeriod);

        $subscription->suspend();

        expect($subscription->status())->toBe(SubscriptionStatus::Suspended);
    });

    test('emits SubscriptionSuspended event', function () {
        $subscription = createSubscription(SubscriptionStatus::GracePeriod);

        $subscription->suspend('non_payment');

        $events = $subscription->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(SubscriptionSuspended::class);
    });

    test('cannot suspend from Active', function () {
        $subscription = createSubscription(SubscriptionStatus::Active);

        $subscription->suspend();
    })->throws(DomainException::class);
});

describe('cancel', function () {
    test('cancels from Active', function () {
        $subscription = createSubscription(SubscriptionStatus::Active);
        $now = new DateTimeImmutable;

        $subscription->cancel($now);

        expect($subscription->status())->toBe(SubscriptionStatus::Canceled)
            ->and($subscription->canceledAt())->toBe($now);
    });

    test('cancels from Trialing', function () {
        $subscription = createSubscription(SubscriptionStatus::Trialing);
        $now = new DateTimeImmutable;

        $subscription->cancel($now);

        expect($subscription->status())->toBe(SubscriptionStatus::Canceled);
    });

    test('cancels from PastDue', function () {
        $subscription = createSubscription(SubscriptionStatus::PastDue);
        $now = new DateTimeImmutable;

        $subscription->cancel($now);

        expect($subscription->status())->toBe(SubscriptionStatus::Canceled);
    });

    test('cancels from GracePeriod', function () {
        $subscription = createSubscription(SubscriptionStatus::GracePeriod);
        $now = new DateTimeImmutable;

        $subscription->cancel($now);

        expect($subscription->status())->toBe(SubscriptionStatus::Canceled);
    });

    test('emits SubscriptionCanceled event', function () {
        $subscription = createSubscription(SubscriptionStatus::Active);
        $now = new DateTimeImmutable;

        $subscription->cancel($now);

        $events = $subscription->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(SubscriptionCanceled::class);
    });

    test('cannot cancel from Expired', function () {
        $subscription = createSubscription(SubscriptionStatus::Expired);

        $subscription->cancel(new DateTimeImmutable);
    })->throws(DomainException::class);
});

describe('expire', function () {
    test('expires from Suspended', function () {
        $subscription = createSubscription(SubscriptionStatus::Suspended);

        $subscription->expire();

        expect($subscription->status())->toBe(SubscriptionStatus::Expired);
    });

    test('expires from Canceled', function () {
        $subscription = createSubscription(SubscriptionStatus::Canceled);

        $subscription->expire();

        expect($subscription->status())->toBe(SubscriptionStatus::Expired);
    });

    test('emits SubscriptionExpired event', function () {
        $subscription = createSubscription(SubscriptionStatus::Suspended);

        $subscription->expire();

        $events = $subscription->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(SubscriptionExpired::class);
    });

    test('cannot expire from Active', function () {
        $subscription = createSubscription(SubscriptionStatus::Active);

        $subscription->expire();
    })->throws(DomainException::class);
});

// --- Renew ---

describe('renew', function () {
    test('renews active subscription with new period', function () {
        $subscription = createSubscription(SubscriptionStatus::Active);
        $newPeriod = createSubscriptionPeriod('2025-02-01', '2025-03-01');

        $subscription->renew($newPeriod);

        expect($subscription->currentPeriod())->toBe($newPeriod)
            ->and($subscription->gracePeriodEnd())->toBeNull();
    });

    test('renew clears gracePeriodEnd', function () {
        // Create PastDue -> GracePeriod -> Active, then renew
        $subscription = createSubscription(SubscriptionStatus::PastDue);
        $subscription->enterGracePeriod(new DateTimeImmutable('2025-02-15'));

        expect($subscription->gracePeriodEnd())->not->toBeNull();

        // Transition back to Active via reactivate
        $subscription->reactivate();
        $subscription->pullDomainEvents(); // clear events

        $newPeriod = createSubscriptionPeriod('2025-02-01', '2025-03-01');
        $subscription->renew($newPeriod);

        expect($subscription->gracePeriodEnd())->toBeNull();
    });

    test('emits SubscriptionRenewed event', function () {
        $subscription = createSubscription(SubscriptionStatus::Active);
        $newPeriod = createSubscriptionPeriod('2025-02-01', '2025-03-01');

        $subscription->renew($newPeriod);

        $events = $subscription->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(SubscriptionRenewed::class);
    });

    test('cannot renew non-active subscription', function () {
        $subscription = createSubscription(SubscriptionStatus::PastDue);
        $newPeriod = createSubscriptionPeriod('2025-02-01', '2025-03-01');

        $subscription->renew($newPeriod);
    })->throws(DomainException::class, 'Only active subscriptions can be renewed');
});

// --- changePlan ---

describe('changePlan', function () {
    test('changes plan for operational subscription', function () {
        $subscription = createSubscription(SubscriptionStatus::Active);
        $newPlanVersionId = Uuid::generate();
        $oldPlanVersionId = $subscription->planVersionId();

        $subscription->changePlan($newPlanVersionId);

        expect($subscription->planVersionId())->toBe($newPlanVersionId)
            ->and($subscription->planVersionId())->not->toBe($oldPlanVersionId);
    });

    test('changes plan for Trialing subscription', function () {
        $subscription = createSubscription(SubscriptionStatus::Trialing);
        $newPlanVersionId = Uuid::generate();

        $subscription->changePlan($newPlanVersionId);

        expect($subscription->planVersionId())->toBe($newPlanVersionId);
    });

    test('changes plan for PastDue subscription', function () {
        $subscription = createSubscription(SubscriptionStatus::PastDue);
        $newPlanVersionId = Uuid::generate();

        $subscription->changePlan($newPlanVersionId);

        expect($subscription->planVersionId())->toBe($newPlanVersionId);
    });

    test('emits SubscriptionPlanChanged event', function () {
        $subscription = createSubscription(SubscriptionStatus::Active);
        $newPlanVersionId = Uuid::generate();

        $subscription->changePlan($newPlanVersionId);

        $events = $subscription->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(SubscriptionPlanChanged::class);
    });

    test('cannot change plan for non-operational subscription', function () {
        $subscription = createSubscription(SubscriptionStatus::Suspended);
        $newPlanVersionId = Uuid::generate();

        $subscription->changePlan($newPlanVersionId);
    })->throws(DomainException::class, 'Cannot change plan for non-operational subscription');
});

// --- reactivate ---

describe('reactivate', function () {
    test('reactivates from GracePeriod', function () {
        $subscription = createSubscription(SubscriptionStatus::GracePeriod);

        $subscription->reactivate();

        expect($subscription->status())->toBe(SubscriptionStatus::Active)
            ->and($subscription->gracePeriodEnd())->toBeNull();
    });

    test('reactivates from PastDue', function () {
        $subscription = createSubscription(SubscriptionStatus::PastDue);

        $subscription->reactivate();

        expect($subscription->status())->toBe(SubscriptionStatus::Active);
    });

    test('reactivates from Suspended', function () {
        $subscription = createSubscription(SubscriptionStatus::Suspended);

        $subscription->reactivate();

        expect($subscription->status())->toBe(SubscriptionStatus::Active);
    });

    test('emits SubscriptionActivated event on reactivation', function () {
        $subscription = createSubscription(SubscriptionStatus::Suspended);

        $subscription->reactivate();

        $events = $subscription->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(SubscriptionActivated::class);
    });

    test('cannot reactivate from Expired', function () {
        $subscription = createSubscription(SubscriptionStatus::Expired);

        $subscription->reactivate();
    })->throws(DomainException::class);
});

// --- Invalid transition error context ---

test('invalid transition throws with correct error code and context', function () {
    $subscription = createSubscription(SubscriptionStatus::Active);

    try {
        $subscription->expire();
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVALID_SUBSCRIPTION_TRANSITION')
            ->and($e->context())->toHaveKey('current_status', 'active')
            ->and($e->context())->toHaveKey('target_status', 'expired')
            ->and($e->context())->toHaveKey('allowed');
    }
});

// --- pullDomainEvents ---

test('pullDomainEvents returns and clears events', function () {
    $subscription = Subscription::create(
        Uuid::generate(),
        Uuid::generate(),
        Uuid::generate(),
        BillingCycle::Monthly,
        createSubscriptionPeriod(),
    );

    $events = $subscription->pullDomainEvents();
    expect($events)->toHaveCount(1);

    $eventsAgain = $subscription->pullDomainEvents();
    expect($eventsAgain)->toBeEmpty();
});

// --- Full lifecycle ---

test('supports full lifecycle: Trialing -> Active -> PastDue -> GracePeriod -> Suspended -> Expired', function () {
    $subscription = createSubscription(SubscriptionStatus::Trialing);

    $subscription->activate();
    expect($subscription->status())->toBe(SubscriptionStatus::Active);

    $subscription->markPastDue();
    expect($subscription->status())->toBe(SubscriptionStatus::PastDue);

    $subscription->enterGracePeriod(new DateTimeImmutable('2025-02-15'));
    expect($subscription->status())->toBe(SubscriptionStatus::GracePeriod);

    $subscription->suspend();
    expect($subscription->status())->toBe(SubscriptionStatus::Suspended);

    $subscription->expire();
    expect($subscription->status())->toBe(SubscriptionStatus::Expired);
});

test('supports cancellation lifecycle: Active -> Canceled -> Expired', function () {
    $subscription = createSubscription(SubscriptionStatus::Active);

    $subscription->cancel(new DateTimeImmutable);
    expect($subscription->status())->toBe(SubscriptionStatus::Canceled);

    $subscription->expire();
    expect($subscription->status())->toBe(SubscriptionStatus::Expired);
});
