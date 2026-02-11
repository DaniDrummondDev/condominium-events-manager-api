<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\InvoiceRepositoryInterface;
use Application\Billing\Contracts\PaymentGatewayInterface;
use Application\Billing\Contracts\PaymentRepositoryInterface;
use Application\Billing\DTOs\ChargeRequestDTO;
use DateTimeImmutable;
use Domain\Billing\Entities\Payment;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ProcessPayment
{
    public function __construct(
        private InvoiceRepositoryInterface $invoiceRepository,
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentGatewayInterface $gateway,
    ) {}

    public function execute(string $invoiceId, string $paymentMethodToken): Payment
    {
        $invoiceUuid = Uuid::fromString($invoiceId);
        $invoice = $this->invoiceRepository->findById($invoiceUuid);

        if ($invoice === null) {
            throw new DomainException(
                'Invoice not found',
                'INVOICE_NOT_FOUND',
                ['invoice_id' => $invoiceId],
            );
        }

        if (! $invoice->status()->isPayable()) {
            throw new DomainException(
                'Invoice is not payable',
                'INVOICE_NOT_PAYABLE',
                ['invoice_id' => $invoiceId, 'status' => $invoice->status()->value],
            );
        }

        $payment = Payment::create(
            Uuid::generate(),
            $invoiceUuid,
            'stripe',
            new Money($invoice->total()->amount(), $invoice->currency()),
        );

        $chargeResult = $this->gateway->charge(new ChargeRequestDTO(
            invoiceId: $invoiceId,
            amountInCents: $invoice->total()->amount(),
            currency: $invoice->currency(),
            paymentMethodToken: $paymentMethodToken,
            metadata: ['tenant_id' => $invoice->tenantId()->value()],
        ));

        if ($chargeResult->success) {
            if ($chargeResult->gatewayTransactionId !== null) {
                $payment->authorize($chargeResult->gatewayTransactionId);
            }

            $now = new DateTimeImmutable;
            $payment->confirmPayment($now);
            $invoice->markPaid($now);

            $this->invoiceRepository->save($invoice);
        } else {
            $payment->fail(new DateTimeImmutable);
        }

        $this->paymentRepository->save($payment);

        return $payment;
    }
}
