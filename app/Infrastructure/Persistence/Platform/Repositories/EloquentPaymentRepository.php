<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\PaymentModel;
use Application\Billing\Contracts\PaymentRepositoryInterface;
use DateTimeImmutable;
use Domain\Billing\Entities\Payment;
use Domain\Billing\Enums\PaymentStatus;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    public function findById(Uuid $id): ?Payment
    {
        $model = PaymentModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<Payment>
     */
    public function findByInvoiceId(Uuid $invoiceId): array
    {
        return PaymentModel::query()
            ->where('invoice_id', $invoiceId->value())
            ->get()
            ->map(fn (PaymentModel $model) => $this->toDomain($model))
            ->all();
    }

    public function findByGatewayTransactionId(string $gateway, string $transactionId): ?Payment
    {
        $model = PaymentModel::query()
            ->where('gateway', $gateway)
            ->where('gateway_transaction_id', $transactionId)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Payment $payment): void
    {
        PaymentModel::query()->updateOrCreate(
            ['id' => $payment->id()->value()],
            [
                'invoice_id' => $payment->invoiceId()->value(),
                'gateway' => $payment->gateway(),
                'gateway_transaction_id' => $payment->gatewayTransactionId(),
                'amount' => $payment->amount()->amount() / 100,
                'currency' => $payment->amount()->currency(),
                'status' => $payment->status()->value,
                'method' => $payment->method(),
                'paid_at' => $payment->paidAt(),
                'failed_at' => $payment->failedAt(),
                'metadata' => $payment->metadata(),
            ],
        );
    }

    private function toDomain(PaymentModel $model): Payment
    {
        $currency = $model->currency ?? 'BRL';

        return new Payment(
            id: Uuid::fromString($model->id),
            invoiceId: Uuid::fromString($model->invoice_id),
            gateway: $model->gateway,
            gatewayTransactionId: $model->gateway_transaction_id,
            amount: new Money((int) round((float) $model->amount * 100), $currency),
            status: PaymentStatus::from($model->status),
            method: $model->method,
            paidAt: $model->paid_at ? new DateTimeImmutable((string) $model->paid_at) : null,
            failedAt: $model->failed_at ? new DateTimeImmutable((string) $model->failed_at) : null,
            metadata: $model->metadata ?? [],
            createdAt: new DateTimeImmutable((string) $model->created_at),
        );
    }
}
