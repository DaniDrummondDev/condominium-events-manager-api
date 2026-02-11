<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use Domain\Billing\Entities\Payment;
use Domain\Shared\ValueObjects\Uuid;

interface PaymentRepositoryInterface
{
    public function findById(Uuid $id): ?Payment;

    /**
     * @return array<Payment>
     */
    public function findByInvoiceId(Uuid $invoiceId): array;

    public function findByGatewayTransactionId(string $gateway, string $transactionId): ?Payment;

    public function save(Payment $payment): void;
}
