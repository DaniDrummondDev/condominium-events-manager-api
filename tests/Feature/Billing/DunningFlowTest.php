<?php

declare(strict_types=1);

use App\Infrastructure\Gateways\Payment\FakePaymentGateway;
use App\Infrastructure\Persistence\Platform\Models\DunningPolicyModel;
use App\Infrastructure\Persistence\Platform\Models\InvoiceModel;
use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use Application\Billing\Contracts\PaymentGatewayInterface;
use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use Tests\Traits\CreatesBillingData;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class, CreatesBillingData::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();

    $this->fakeGateway = new FakePaymentGateway;
    app()->instance(PaymentGatewayInterface::class, $this->fakeGateway);

    TenantModel::query()->create([
        'id' => $this->tenantId = \Domain\Shared\ValueObjects\Uuid::generate()->value(),
        'slug' => 'dunning-condo',
        'name' => 'Dunning Test Condo',
        'type' => 'vertical',
        'status' => 'active',
    ]);

    $data = $this->createPlanInDatabase('Starter', 'starter-dun', 9900);

    $createSub = app(\Application\Billing\UseCases\CreateSubscription::class);
    $sub = $createSub->execute(new \Application\Billing\DTOs\CreateSubscriptionDTO(
        tenantId: $this->tenantId,
        planVersionId: $data['version']->id,
        billingCycle: 'monthly',
    ));
    $this->subscriptionId = $sub->id;

    $genInvoice = app(\Application\Billing\UseCases\GenerateInvoice::class);
    $this->invoice = $genInvoice->execute(new \Application\Billing\DTOs\GenerateInvoiceDTO(
        subscriptionId: $sub->id,
        periodStart: $sub->currentPeriodStart,
        periodEnd: $sub->currentPeriodEnd,
    ));

    // Create default dunning policy
    DunningPolicyModel::query()->create([
        'id' => \Domain\Shared\ValueObjects\Uuid::generate()->value(),
        'name' => 'Default Policy',
        'max_retries' => 3,
        'retry_intervals' => [1, 3, 7],
        'suspend_after_days' => 15,
        'cancel_after_days' => 30,
        'is_default' => true,
    ]);
});

test('dunning processes past due invoices', function () {
    // Mark invoice as past_due by updating directly
    InvoiceModel::query()
        ->where('id', $this->invoice->id)
        ->update([
            'status' => 'past_due',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

    $useCase = app(\Application\Billing\UseCases\ProcessDunning::class);
    $result = $useCase->execute();

    expect($result['processed'])->toBe(1)
        ->and($result['suspended'])->toBe(0);
});

test('dunning suspends subscription after suspend_after_days', function () {
    // Mark invoice as past_due with due date 20 days ago (> 15 suspend_after_days)
    InvoiceModel::query()
        ->where('id', $this->invoice->id)
        ->update([
            'status' => 'past_due',
            'due_date' => now()->subDays(20)->toDateString(),
        ]);

    $useCase = app(\Application\Billing\UseCases\ProcessDunning::class);
    $result = $useCase->execute();

    expect($result['processed'])->toBe(1)
        ->and($result['suspended'])->toBe(1);

    // Verify subscription is suspended
    $subRepo = app(SubscriptionRepositoryInterface::class);
    $subscription = $subRepo->findById(
        \Domain\Shared\ValueObjects\Uuid::fromString($this->subscriptionId),
    );

    expect($subscription->status()->value)->toBe('suspended');
});

test('dunning does nothing without default policy', function () {
    // Remove the default policy
    DunningPolicyModel::query()->delete();

    InvoiceModel::query()
        ->where('id', $this->invoice->id)
        ->update([
            'status' => 'past_due',
            'due_date' => now()->subDays(20)->toDateString(),
        ]);

    $useCase = app(\Application\Billing\UseCases\ProcessDunning::class);
    $result = $useCase->execute();

    expect($result['processed'])->toBe(0)
        ->and($result['suspended'])->toBe(0);
});

test('dunning does not suspend when within grace period', function () {
    // Mark invoice past_due but only 5 days ago (< 15 suspend_after_days)
    InvoiceModel::query()
        ->where('id', $this->invoice->id)
        ->update([
            'status' => 'past_due',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

    $useCase = app(\Application\Billing\UseCases\ProcessDunning::class);
    $result = $useCase->execute();

    expect($result['suspended'])->toBe(0);

    // Verify subscription is still active
    $subRepo = app(SubscriptionRepositoryInterface::class);
    $subscription = $subRepo->findById(
        \Domain\Shared\ValueObjects\Uuid::fromString($this->subscriptionId),
    );

    expect($subscription->status()->value)->toBe('active');
});
