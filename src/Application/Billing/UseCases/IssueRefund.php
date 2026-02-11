<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\PaymentGatewayInterface;
use Application\Billing\Contracts\PaymentRepositoryInterface;
use Application\Billing\DTOs\RefundRequestDTO;
use Domain\Billing\Entities\Payment;
use Domain\Billing\Enums\PaymentStatus;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class IssueRefund
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentGatewayInterface $gateway,
    ) {}

    public function execute(string $paymentId, int $amountInCents, string $reason): Payment
    {
        $paymentUuid = Uuid::fromString($paymentId);
        $payment = $this->paymentRepository->findById($paymentUuid);

        if ($payment === null) {
            throw new DomainException(
                'Payment not found',
                'PAYMENT_NOT_FOUND',
                ['payment_id' => $paymentId],
            );
        }

        if ($payment->status() !== PaymentStatus::Paid) {
            throw new DomainException(
                'Only confirmed payments can be refunded',
                'PAYMENT_NOT_CONFIRMED',
                ['payment_id' => $paymentId, 'status' => $payment->status()->value],
            );
        }

        if ($amountInCents > $payment->amount()->amount()) {
            throw new DomainException(
                'Refund amount exceeds payment amount',
                'REFUND_EXCEEDS_PAYMENT',
                [
                    'payment_id' => $paymentId,
                    'payment_amount' => $payment->amount()->amount(),
                    'refund_amount' => $amountInCents,
                ],
            );
        }

        if ($payment->gatewayTransactionId() === null) {
            throw new DomainException(
                'Payment has no gateway transaction',
                'NO_GATEWAY_TRANSACTION',
                ['payment_id' => $paymentId],
            );
        }

        $refundResult = $this->gateway->refund(new RefundRequestDTO(
            gatewayTransactionId: $payment->gatewayTransactionId(),
            amountInCents: $amountInCents,
            reason: $reason,
        ));

        if (! $refundResult->success) {
            throw new DomainException(
                'Refund failed at gateway',
                'GATEWAY_REFUND_FAILED',
                ['payment_id' => $paymentId, 'error' => $refundResult->errorMessage],
            );
        }

        $payment->refund();
        $this->paymentRepository->save($payment);

        return $payment;
    }
}
