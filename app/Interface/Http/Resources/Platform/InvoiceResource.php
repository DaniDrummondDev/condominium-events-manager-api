<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Platform;

use Application\Billing\DTOs\InvoiceDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property InvoiceDTO $resource
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'tenant_id' => $this->resource->tenantId,
            'subscription_id' => $this->resource->subscriptionId,
            'invoice_number' => $this->resource->invoiceNumber,
            'status' => $this->resource->status,
            'currency' => $this->resource->currency,
            'subtotal_in_cents' => $this->resource->subtotalInCents,
            'tax_amount_in_cents' => $this->resource->taxAmountInCents,
            'discount_amount_in_cents' => $this->resource->discountAmountInCents,
            'total_in_cents' => $this->resource->totalInCents,
            'due_date' => $this->resource->dueDate,
            'paid_at' => $this->resource->paidAt,
            'voided_at' => $this->resource->voidedAt,
            'created_at' => $this->resource->createdAt,
            'items' => array_map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price_in_cents' => $item->unitPriceInCents,
                'total_in_cents' => $item->totalInCents,
            ], $this->resource->items),
        ];
    }
}
