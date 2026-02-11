<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\GatewayEventRepositoryInterface;
use Application\Billing\Contracts\InvoiceRepositoryInterface;
use Application\Billing\Contracts\PaymentGatewayInterface;
use Application\Billing\Contracts\PaymentRepositoryInterface;
use Application\Billing\DTOs\GatewayEventRecord;
use Application\Billing\DTOs\WebhookPayloadDTO;
use DateTimeImmutable;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class HandlePaymentWebhook
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private PaymentRepositoryInterface $paymentRepository,
        private InvoiceRepositoryInterface $invoiceRepository,
        private GatewayEventRepositoryInterface $gatewayEventRepository,
    ) {}

    public function execute(string $rawPayload, string $signature, WebhookPayloadDTO $dto): void
    {
        if (! $this->gateway->verifyWebhookSignature($rawPayload, $signature)) {
            throw new DomainException(
                'Invalid webhook signature',
                'INVALID_WEBHOOK_SIGNATURE',
            );
        }

        $idempotencyKey = "{$dto->gateway}:{$dto->gatewayTransactionId}:{$dto->eventType}";

        $existing = $this->gatewayEventRepository->findByIdempotencyKey($idempotencyKey);

        if ($existing !== null) {
            return;
        }

        $this->gatewayEventRepository->save(new GatewayEventRecord(
            id: Uuid::generate()->value(),
            gateway: $dto->gateway,
            eventType: $dto->eventType,
            payload: $dto->metadata,
            idempotencyKey: $idempotencyKey,
            processedAt: new DateTimeImmutable,
        ));

        $payment = $this->paymentRepository->findByGatewayTransactionId(
            $dto->gateway,
            $dto->gatewayTransactionId,
        );

        if ($payment === null) {
            return;
        }

        match ($dto->status) {
            'paid', 'succeeded' => $this->handlePaymentConfirmed($payment),
            'failed' => $this->handlePaymentFailed($payment),
            default => null,
        };
    }

    private function handlePaymentConfirmed(\Domain\Billing\Entities\Payment $payment): void
    {
        if ($payment->status()->isSuccessful()) {
            return;
        }

        $now = new DateTimeImmutable;
        $payment->confirmPayment($now);
        $this->paymentRepository->save($payment);

        $invoice = $this->invoiceRepository->findById($payment->invoiceId());

        if ($invoice !== null && $invoice->status()->isPayable()) {
            $invoice->markPaid($now);
            $this->invoiceRepository->save($invoice);
        }
    }

    private function handlePaymentFailed(\Domain\Billing\Entities\Payment $payment): void
    {
        if ($payment->status()->isSuccessful()) {
            return;
        }

        $payment->fail(new DateTimeImmutable);
        $this->paymentRepository->save($payment);
    }
}
