<?php

declare(strict_types=1);

use Domain\Billing\Entities\Plan;
use Domain\Billing\Enums\PlanStatus;
use Domain\Billing\Events\PlanCreated;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

function createPlan(PlanStatus $status = PlanStatus::Active): Plan
{
    return new Plan(
        id: Uuid::generate(),
        name: 'Plano Essencial',
        slug: 'plano-essencial',
        status: $status,
    );
}

// --- Factory method ---

describe('create', function () {
    test('creates active plan with PlanCreated event', function () {
        $id = Uuid::generate();
        $plan = Plan::create($id, 'Plano Premium', 'plano-premium');

        expect($plan->id())->toBe($id)
            ->and($plan->name())->toBe('Plano Premium')
            ->and($plan->slug())->toBe('plano-premium')
            ->and($plan->status())->toBe(PlanStatus::Active);

        $events = $plan->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(PlanCreated::class);
    });
});

// --- rename ---

test('rename changes the name', function () {
    $plan = createPlan();

    $plan->rename('Novo Nome');

    expect($plan->name())->toBe('Novo Nome');
});

// --- activate ---

describe('activate', function () {
    test('activates inactive plan', function () {
        $plan = createPlan(PlanStatus::Inactive);

        $plan->activate();

        expect($plan->status())->toBe(PlanStatus::Active);
    });

    test('activating already active plan keeps Active status', function () {
        $plan = createPlan(PlanStatus::Active);

        $plan->activate();

        expect($plan->status())->toBe(PlanStatus::Active);
    });

    test('cannot activate archived plan', function () {
        $plan = createPlan(PlanStatus::Archived);

        $plan->activate();
    })->throws(DomainException::class, 'Cannot activate an archived plan');
});

// --- deactivate ---

describe('deactivate', function () {
    test('deactivates active plan', function () {
        $plan = createPlan(PlanStatus::Active);

        $plan->deactivate();

        expect($plan->status())->toBe(PlanStatus::Inactive);
    });

    test('deactivating already inactive plan keeps Inactive status', function () {
        $plan = createPlan(PlanStatus::Inactive);

        $plan->deactivate();

        expect($plan->status())->toBe(PlanStatus::Inactive);
    });

    test('cannot deactivate archived plan', function () {
        $plan = createPlan(PlanStatus::Archived);

        $plan->deactivate();
    })->throws(DomainException::class, 'Cannot deactivate an archived plan');
});

// --- archive ---

describe('archive', function () {
    test('archives active plan', function () {
        $plan = createPlan(PlanStatus::Active);

        $plan->archive();

        expect($plan->status())->toBe(PlanStatus::Archived);
    });

    test('archives inactive plan', function () {
        $plan = createPlan(PlanStatus::Inactive);

        $plan->archive();

        expect($plan->status())->toBe(PlanStatus::Archived);
    });

    test('archiving already archived plan keeps Archived status', function () {
        $plan = createPlan(PlanStatus::Archived);

        $plan->archive();

        expect($plan->status())->toBe(PlanStatus::Archived);
    });
});

// --- isAvailable ---

describe('isAvailable', function () {
    test('active plan is available', function () {
        $plan = createPlan(PlanStatus::Active);

        expect($plan->isAvailable())->toBeTrue();
    });

    test('inactive plan is not available', function () {
        $plan = createPlan(PlanStatus::Inactive);

        expect($plan->isAvailable())->toBeFalse();
    });

    test('archived plan is not available', function () {
        $plan = createPlan(PlanStatus::Archived);

        expect($plan->isAvailable())->toBeFalse();
    });
});

// --- pullDomainEvents ---

test('pullDomainEvents returns and clears events', function () {
    $plan = Plan::create(Uuid::generate(), 'Test', 'test');

    $events = $plan->pullDomainEvents();
    expect($events)->toHaveCount(1);

    $eventsAgain = $plan->pullDomainEvents();
    expect($eventsAgain)->toBeEmpty();
});

// --- Full lifecycle ---

test('supports lifecycle: Active -> Inactive -> Active -> Archived', function () {
    $plan = createPlan(PlanStatus::Active);

    $plan->deactivate();
    expect($plan->status())->toBe(PlanStatus::Inactive);

    $plan->activate();
    expect($plan->status())->toBe(PlanStatus::Active);

    $plan->archive();
    expect($plan->status())->toBe(PlanStatus::Archived);
});

test('archived plan cannot be reactivated or deactivated', function () {
    $plan = createPlan(PlanStatus::Archived);

    $activateThrows = false;
    $deactivateThrows = false;

    try {
        $plan->activate();
    } catch (DomainException) {
        $activateThrows = true;
    }

    try {
        $plan->deactivate();
    } catch (DomainException) {
        $deactivateThrows = true;
    }

    expect($activateThrows)->toBeTrue()
        ->and($deactivateThrows)->toBeTrue();
});
