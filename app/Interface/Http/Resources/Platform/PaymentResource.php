<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Platform;

use Domain\Billing\Entities\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Payment $resource
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id()->value(),
            'invoice_id' => $this->resource->invoiceId()->value(),
            'gateway' => $this->resource->gateway(),
            'gateway_transaction_id' => $this->resource->gatewayTransactionId(),
            'amount_in_cents' => $this->resource->amount()->amount(),
            'currency' => $this->resource->amount()->currency(),
            'status' => $this->resource->status()->value,
            'method' => $this->resource->method(),
            'paid_at' => $this->resource->paidAt()?->format('c'),
            'failed_at' => $this->resource->failedAt()?->format('c'),
            'created_at' => $this->resource->createdAt()->format('c'),
        ];
    }
}
