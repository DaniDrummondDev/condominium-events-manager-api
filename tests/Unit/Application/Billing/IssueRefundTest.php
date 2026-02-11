<?php

declare(strict_types=1);

use Application\Billing\Contracts\PaymentGatewayInterface;
use Application\Billing\Contracts\PaymentRepositoryInterface;
use Application\Billing\DTOs\RefundResultDTO;
use Application\Billing\UseCases\IssueRefund;
use Domain\Billing\Entities\Payment;
use Domain\Billing\Enums\PaymentStatus;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

function createPaidPayment(
    ?Uuid $id = null,
    int $amountInCents = 9900,
    string $gatewayTxnId = 'txn_paid_123',
): Payment {
    $payment = Payment::create(
        $id ?? Uuid::generate(),
        Uuid::generate(),
        'stripe',
        new Money($amountInCents, 'BRL'),
    );

    $payment->authorize($gatewayTxnId);
    $payment->confirmPayment(new DateTimeImmutable);

    return $payment;
}

test('issues a refund successfully', function () {
    $paymentId = Uuid::generate();
    $payment = createPaidPayment($paymentId, 9900);

    $refundResult = new RefundResultDTO(
        success: true,
        refundId: 'refund_123',
    );

    $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
    $paymentRepo->expects('findById')->andReturn($payment);
    $paymentRepo->expects('save')->once();

    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->expects('refund')->andReturn($refundResult);

    $useCase = new IssueRefund($paymentRepo, $gateway);
    $result = $useCase->execute($paymentId->value(), 5000, 'Customer request');

    expect($result)->toBeInstanceOf(Payment::class)
        ->and($result->status())->toBe(PaymentStatus::Refunded);
});

test('throws when refund amount exceeds payment amount', function () {
    $paymentId = Uuid::generate();
    $payment = createPaidPayment($paymentId, 5000);

    $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
    $paymentRepo->expects('findById')->andReturn($payment);

    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldNotReceive('refund');

    $useCase = new IssueRefund($paymentRepo, $gateway);

    try {
        $useCase->execute($paymentId->value(), 10000, 'Customer request');
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('REFUND_EXCEEDS_PAYMENT')
            ->and($e->context())->toHaveKey('payment_amount', 5000)
            ->and($e->context())->toHaveKey('refund_amount', 10000);
    }
});

test('throws when payment is not confirmed', function () {
    $paymentId = Uuid::generate();

    // Create a pending payment (not confirmed)
    $payment = Payment::create(
        $paymentId,
        Uuid::generate(),
        'stripe',
        new Money(9900, 'BRL'),
    );

    $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
    $paymentRepo->expects('findById')->andReturn($payment);

    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldNotReceive('refund');

    $useCase = new IssueRefund($paymentRepo, $gateway);

    try {
        $useCase->execute($paymentId->value(), 5000, 'Customer request');
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('PAYMENT_NOT_CONFIRMED')
            ->and($e->context())->toHaveKey('payment_id', $paymentId->value())
            ->and($e->context())->toHaveKey('status', PaymentStatus::Pending->value);
    }
});
