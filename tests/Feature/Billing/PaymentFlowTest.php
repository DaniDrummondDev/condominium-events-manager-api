<?php

declare(strict_types=1);

use App\Infrastructure\Gateways\Payment\FakePaymentGateway;
use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use Application\Billing\Contracts\PaymentGatewayInterface;
use Tests\Traits\CreatesBillingData;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class, CreatesBillingData::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();

    $this->fakeGateway = new FakePaymentGateway;
    app()->instance(PaymentGatewayInterface::class, $this->fakeGateway);

    TenantModel::query()->create([
        'id' => $this->tenantId = \Domain\Shared\ValueObjects\Uuid::generate()->value(),
        'slug' => 'payment-condo',
        'name' => 'Payment Test Condo',
        'type' => 'vertical',
        'status' => 'active',
    ]);

    $data = $this->createPlanInDatabase('Starter', 'starter-pay', 9900);

    $createSub = app(\Application\Billing\UseCases\CreateSubscription::class);
    $sub = $createSub->execute(new \Application\Billing\DTOs\CreateSubscriptionDTO(
        tenantId: $this->tenantId,
        planVersionId: $data['version']->id,
        billingCycle: 'monthly',
    ));

    $genInvoice = app(\Application\Billing\UseCases\GenerateInvoice::class);
    $this->invoice = $genInvoice->execute(new \Application\Billing\DTOs\GenerateInvoiceDTO(
        subscriptionId: $sub->id,
        periodStart: $sub->currentPeriodStart,
        periodEnd: $sub->currentPeriodEnd,
    ));
});

test('processes payment successfully via FakeGateway', function () {
    $useCase = app(\Application\Billing\UseCases\ProcessPayment::class);

    $payment = $useCase->execute($this->invoice->id, 'tok_test_123');

    expect($payment->status()->value)->toBe('paid')
        ->and($payment->gatewayTransactionId())->toStartWith('fake_tx_')
        ->and($payment->paidAt())->not->toBeNull();

    // Verify invoice is marked as paid
    $invoiceRepo = app(\Application\Billing\Contracts\InvoiceRepositoryInterface::class);
    $updatedInvoice = $invoiceRepo->findById(\Domain\Shared\ValueObjects\Uuid::fromString($this->invoice->id));

    expect($updatedInvoice->status()->value)->toBe('paid');
});

test('handles payment failure from gateway', function () {
    $this->fakeGateway->setShouldSucceed(false);

    $useCase = app(\Application\Billing\UseCases\ProcessPayment::class);

    $payment = $useCase->execute($this->invoice->id, 'tok_failing_123');

    expect($payment->status()->value)->toBe('failed')
        ->and($payment->failedAt())->not->toBeNull();

    // Invoice should still be open
    $invoiceRepo = app(\Application\Billing\Contracts\InvoiceRepositoryInterface::class);
    $invoice = $invoiceRepo->findById(\Domain\Shared\ValueObjects\Uuid::fromString($this->invoice->id));

    expect($invoice->status()->value)->toBe('open');
});

test('FakeGateway records charges', function () {
    $useCase = app(\Application\Billing\UseCases\ProcessPayment::class);

    $useCase->execute($this->invoice->id, 'tok_test_456');

    $charges = $this->fakeGateway->getCharges();

    expect($charges)->toHaveCount(1)
        ->and($charges[0]->amountInCents)->toBe(9900)
        ->and($charges[0]->currency)->toBe('BRL');
});
