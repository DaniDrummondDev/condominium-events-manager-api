<?php

declare(strict_types=1);

use App\Infrastructure\Gateways\Payment\FakePaymentGateway;
use App\Infrastructure\Persistence\Platform\Models\TenantModel;
use Application\Billing\Contracts\PaymentGatewayInterface;
use Application\Billing\DTOs\WebhookPayloadDTO;
use Domain\Shared\Exceptions\DomainException;
use Tests\Traits\CreatesBillingData;
use Tests\Traits\UsesPlatformDatabase;

uses(UsesPlatformDatabase::class, CreatesBillingData::class);

beforeEach(function () {
    $this->setUpPlatformDatabase();

    $this->fakeGateway = new FakePaymentGateway;
    app()->instance(PaymentGatewayInterface::class, $this->fakeGateway);

    TenantModel::query()->create([
        'id' => $this->tenantId = \Domain\Shared\ValueObjects\Uuid::generate()->value(),
        'slug' => 'webhook-condo',
        'name' => 'Webhook Test Condo',
        'type' => 'vertical',
        'status' => 'active',
    ]);

    $data = $this->createPlanInDatabase('Starter', 'starter-wh', 9900);

    $createSub = app(\Application\Billing\UseCases\CreateSubscription::class);
    $sub = $createSub->execute(new \Application\Billing\DTOs\CreateSubscriptionDTO(
        tenantId: $this->tenantId,
        planVersionId: $data['version']->id,
        billingCycle: 'monthly',
    ));

    $genInvoice = app(\Application\Billing\UseCases\GenerateInvoice::class);
    $invoice = $genInvoice->execute(new \Application\Billing\DTOs\GenerateInvoiceDTO(
        subscriptionId: $sub->id,
        periodStart: $sub->currentPeriodStart,
        periodEnd: $sub->currentPeriodEnd,
    ));

    $processPayment = app(\Application\Billing\UseCases\ProcessPayment::class);
    $this->payment = $processPayment->execute($invoice->id, 'tok_test_wh');
    $this->invoiceId = $invoice->id;
});

test('payment is confirmed in setup and invoice is paid', function () {
    // The beforeEach creates a payment that goes through gateway successfully
    expect($this->payment->status()->value)->toBe('paid')
        ->and($this->payment->gatewayTransactionId())->toStartWith('fake_tx_');

    // Verify invoice is also paid
    $invoiceRepo = app(\Application\Billing\Contracts\InvoiceRepositoryInterface::class);
    $invoice = $invoiceRepo->findById(\Domain\Shared\ValueObjects\Uuid::fromString($this->invoiceId));

    expect($invoice->status()->value)->toBe('paid');
});

test('webhook rejects invalid signature', function () {
    $useCase = app(\Application\Billing\UseCases\HandlePaymentWebhook::class);

    $useCase->execute(
        rawPayload: '{"event":"payment.confirmed"}',
        signature: 'invalid_signature',
        dto: new WebhookPayloadDTO(
            gateway: 'stripe',
            eventType: 'payment.confirmed',
            gatewayTransactionId: 'fake_tx_123',
            status: 'paid',
            amountInCents: 9900,
        ),
    );
})->throws(DomainException::class, 'Invalid webhook signature');

test('webhook with valid signature is accepted', function () {
    $useCase = app(\Application\Billing\UseCases\HandlePaymentWebhook::class);

    // This should not throw — payment may not be found but signature is valid
    $useCase->execute(
        rawPayload: '{"event":"payment.confirmed"}',
        signature: 'valid_signature',
        dto: new WebhookPayloadDTO(
            gateway: 'stripe',
            eventType: 'payment.confirmed',
            gatewayTransactionId: 'nonexistent_tx',
            status: 'paid',
            amountInCents: 9900,
        ),
    );

    expect(true)->toBeTrue();
});

test('webhook is idempotent for duplicate events', function () {
    $useCase = app(\Application\Billing\UseCases\HandlePaymentWebhook::class);

    $dto = new WebhookPayloadDTO(
        gateway: 'stripe',
        eventType: 'payment.confirmed',
        gatewayTransactionId: 'fake_tx_idempotent',
        status: 'paid',
        amountInCents: 9900,
    );

    // First call
    $useCase->execute(
        rawPayload: '{"event":"payment.confirmed"}',
        signature: 'valid_signature',
        dto: $dto,
    );

    // Second call with same data — should be idempotent (no error)
    $useCase->execute(
        rawPayload: '{"event":"payment.confirmed"}',
        signature: 'valid_signature',
        dto: $dto,
    );

    expect(true)->toBeTrue();
});
