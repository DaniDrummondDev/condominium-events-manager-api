<?php

declare(strict_types=1);

use Application\Billing\Contracts\GatewayEventRepositoryInterface;
use Application\Billing\Contracts\InvoiceRepositoryInterface;
use Application\Billing\Contracts\PaymentGatewayInterface;
use Application\Billing\Contracts\PaymentRepositoryInterface;
use Application\Billing\DTOs\GatewayEventRecord;
use Application\Billing\DTOs\WebhookPayloadDTO;
use Application\Billing\UseCases\HandlePaymentWebhook;
use Domain\Billing\Entities\Payment;
use Domain\Billing\Enums\PaymentStatus;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

function createPendingPaymentWithTransaction(
    ?Uuid $invoiceId = null,
    string $gatewayTxnId = 'txn_abc123',
): Payment {
    $payment = Payment::create(
        Uuid::generate(),
        $invoiceId ?? Uuid::generate(),
        'stripe',
        new Money(9900, 'BRL'),
    );

    $payment->authorize($gatewayTxnId);

    return $payment;
}

test('processes webhook with valid signature successfully', function () {
    $invoiceId = Uuid::generate();
    $payment = createPendingPaymentWithTransaction($invoiceId);

    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->expects('verifyWebhookSignature')->andReturnTrue();

    $gatewayEventRepo = Mockery::mock(GatewayEventRepositoryInterface::class);
    $gatewayEventRepo->expects('findByIdempotencyKey')->andReturnNull();
    $gatewayEventRepo->expects('save')->once();

    $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
    $paymentRepo->expects('findByGatewayTransactionId')->andReturn($payment);
    $paymentRepo->expects('save')->once();

    $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);

    // Create an open invoice for markPaid
    $invoice = \Domain\Billing\Entities\Invoice::create(
        $invoiceId,
        Uuid::generate(),
        Uuid::generate(),
        new \Domain\Billing\ValueObjects\InvoiceNumber(2025, 1),
        'BRL',
        new DateTimeImmutable('2025-01-01'),
    );
    $invoice->issue();

    $invoiceRepo->expects('findById')->andReturn($invoice);
    $invoiceRepo->expects('save')->once();

    $useCase = new HandlePaymentWebhook($gateway, $paymentRepo, $invoiceRepo, $gatewayEventRepo);

    $dto = new WebhookPayloadDTO(
        gateway: 'stripe',
        eventType: 'payment_intent.succeeded',
        gatewayTransactionId: 'txn_abc123',
        status: 'paid',
        amountInCents: 9900,
    );

    $useCase->execute('{"type":"payment_intent.succeeded"}', 'sig_valid', $dto);

    expect($payment->status())->toBe(PaymentStatus::Paid);
});

test('throws when webhook signature is invalid', function () {
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->expects('verifyWebhookSignature')->andReturnFalse();

    $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
    $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
    $gatewayEventRepo = Mockery::mock(GatewayEventRepositoryInterface::class);

    $useCase = new HandlePaymentWebhook($gateway, $paymentRepo, $invoiceRepo, $gatewayEventRepo);

    $dto = new WebhookPayloadDTO(
        gateway: 'stripe',
        eventType: 'payment_intent.succeeded',
        gatewayTransactionId: 'txn_abc123',
        status: 'paid',
        amountInCents: 9900,
    );

    try {
        $useCase->execute('{"type":"payment_intent.succeeded"}', 'sig_invalid', $dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVALID_WEBHOOK_SIGNATURE');
    }
});

test('skips processing when event was already handled (idempotent)', function () {
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->expects('verifyWebhookSignature')->andReturnTrue();

    $existingEvent = new GatewayEventRecord(
        id: Uuid::generate()->value(),
        gateway: 'stripe',
        eventType: 'payment_intent.succeeded',
        payload: [],
        idempotencyKey: 'stripe:txn_abc123:payment_intent.succeeded',
        processedAt: new DateTimeImmutable,
    );

    $gatewayEventRepo = Mockery::mock(GatewayEventRepositoryInterface::class);
    $gatewayEventRepo->expects('findByIdempotencyKey')->andReturn($existingEvent);
    $gatewayEventRepo->shouldNotReceive('save');

    $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
    $paymentRepo->shouldNotReceive('findByGatewayTransactionId');

    $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);

    $useCase = new HandlePaymentWebhook($gateway, $paymentRepo, $invoiceRepo, $gatewayEventRepo);

    $dto = new WebhookPayloadDTO(
        gateway: 'stripe',
        eventType: 'payment_intent.succeeded',
        gatewayTransactionId: 'txn_abc123',
        status: 'paid',
        amountInCents: 9900,
    );

    $useCase->execute('{"type":"payment_intent.succeeded"}', 'sig_valid', $dto);

    // No exception means idempotent return was successful
    expect(true)->toBeTrue();
});
