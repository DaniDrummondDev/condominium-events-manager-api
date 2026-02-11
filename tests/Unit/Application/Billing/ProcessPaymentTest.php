<?php

declare(strict_types=1);

use Application\Billing\Contracts\InvoiceRepositoryInterface;
use Application\Billing\Contracts\PaymentGatewayInterface;
use Application\Billing\Contracts\PaymentRepositoryInterface;
use Application\Billing\DTOs\ChargeResultDTO;
use Application\Billing\UseCases\ProcessPayment;
use Domain\Billing\Entities\Invoice;
use Domain\Billing\Entities\Payment;
use Domain\Billing\Enums\InvoiceStatus;
use Domain\Billing\Enums\PaymentStatus;
use Domain\Billing\ValueObjects\InvoiceNumber;
use Domain\Shared\ValueObjects\Uuid;

function createOpenInvoice(
    ?Uuid $id = null,
    ?Uuid $tenantId = null,
    ?Uuid $subscriptionId = null,
    int $totalInCents = 9900,
): Invoice {
    $invoiceId = $id ?? Uuid::generate();
    $invoice = Invoice::create(
        $invoiceId,
        $tenantId ?? Uuid::generate(),
        $subscriptionId ?? Uuid::generate(),
        new InvoiceNumber(2025, 1),
        'BRL',
        new DateTimeImmutable('2025-01-01'),
    );

    // Add an item so totals can be calculated
    $item = \Domain\Billing\Entities\InvoiceItem::create(
        Uuid::generate(),
        $invoiceId,
        \Domain\Billing\Enums\InvoiceItemType::Plan,
        'Subscription — Mensal',
        1,
        new \Domain\Shared\ValueObjects\Money($totalInCents, 'BRL'),
    );

    $invoice->addItem($item);
    $invoice->calculateTotals();
    $invoice->issue();

    return $invoice;
}

test('processes payment successfully when gateway returns success', function () {
    $invoiceId = Uuid::generate();
    $invoice = createOpenInvoice($invoiceId);

    $chargeResult = new ChargeResultDTO(
        success: true,
        gatewayTransactionId: 'txn_123456',
        status: 'succeeded',
    );

    $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
    $invoiceRepo->expects('findById')->andReturn($invoice);
    $invoiceRepo->expects('save')->once();

    $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
    $paymentRepo->expects('save')->once();

    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->expects('charge')->andReturn($chargeResult);

    $useCase = new ProcessPayment($invoiceRepo, $paymentRepo, $gateway);
    $result = $useCase->execute($invoiceId->value(), 'pm_token_123');

    expect($result)->toBeInstanceOf(Payment::class)
        ->and($result->status())->toBe(PaymentStatus::Paid)
        ->and($result->gatewayTransactionId())->toBe('txn_123456')
        ->and($result->paidAt())->not()->toBeNull();

    expect($invoice->status())->toBe(InvoiceStatus::Paid);
});

test('marks payment as failed when gateway returns failure', function () {
    $invoiceId = Uuid::generate();
    $invoice = createOpenInvoice($invoiceId);

    $chargeResult = new ChargeResultDTO(
        success: false,
        errorMessage: 'Insufficient funds',
    );

    $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
    $invoiceRepo->expects('findById')->andReturn($invoice);
    $invoiceRepo->shouldNotReceive('save');

    $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
    $paymentRepo->expects('save')->once();

    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->expects('charge')->andReturn($chargeResult);

    $useCase = new ProcessPayment($invoiceRepo, $paymentRepo, $gateway);
    $result = $useCase->execute($invoiceId->value(), 'pm_token_123');

    expect($result)->toBeInstanceOf(Payment::class)
        ->and($result->status())->toBe(PaymentStatus::Failed)
        ->and($result->failedAt())->not()->toBeNull();

    expect($invoice->status())->toBe(InvoiceStatus::Open);
});
