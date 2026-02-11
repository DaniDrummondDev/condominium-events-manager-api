<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\InvoiceItemModel;
use App\Infrastructure\Persistence\Platform\Models\InvoiceModel;
use Application\Billing\Contracts\InvoiceRepositoryInterface;
use DateTimeImmutable;
use Domain\Billing\Entities\Invoice;
use Domain\Billing\Entities\InvoiceItem;
use Domain\Billing\Enums\InvoiceItemType;
use Domain\Billing\Enums\InvoiceStatus;
use Domain\Billing\ValueObjects\InvoiceNumber;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function findById(Uuid $id): ?Invoice
    {
        $model = InvoiceModel::query()->with('items')->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<Invoice>
     */
    public function findByTenantId(Uuid $tenantId): array
    {
        return InvoiceModel::query()
            ->with('items')
            ->where('tenant_id', $tenantId->value())
            ->get()
            ->map(fn (InvoiceModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<Invoice>
     */
    public function findBySubscriptionId(Uuid $subscriptionId): array
    {
        return InvoiceModel::query()
            ->with('items')
            ->where('subscription_id', $subscriptionId->value())
            ->get()
            ->map(fn (InvoiceModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<Invoice>
     */
    public function findPastDue(): array
    {
        return InvoiceModel::query()
            ->with('items')
            ->where('status', InvoiceStatus::PastDue->value)
            ->get()
            ->map(fn (InvoiceModel $model) => $this->toDomain($model))
            ->all();
    }

    public function findBySubscriptionAndPeriod(
        Uuid $subscriptionId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
    ): ?Invoice {
        $model = InvoiceModel::query()
            ->with('items')
            ->where('subscription_id', $subscriptionId->value())
            ->whereDate('due_date', $periodStart->format('Y-m-d'))
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Invoice $invoice): void
    {
        InvoiceModel::query()->updateOrCreate(
            ['id' => $invoice->id()->value()],
            [
                'tenant_id' => $invoice->tenantId()->value(),
                'subscription_id' => $invoice->subscriptionId()->value(),
                'invoice_number' => $invoice->invoiceNumber()->value(),
                'status' => $invoice->status()->value,
                'currency' => $invoice->currency(),
                'subtotal' => $invoice->subtotal()->amount() / 100,
                'tax_amount' => $invoice->taxAmount()->amount() / 100,
                'discount_amount' => $invoice->discountAmount()->amount() / 100,
                'total' => $invoice->total()->amount() / 100,
                'due_date' => $invoice->dueDate()->format('Y-m-d'),
                'paid_at' => $invoice->paidAt(),
                'voided_at' => $invoice->voidedAt(),
            ],
        );

        foreach ($invoice->items() as $item) {
            InvoiceItemModel::query()->updateOrCreate(
                ['id' => $item->id()->value()],
                [
                    'invoice_id' => $item->invoiceId()->value(),
                    'type' => $item->type()->value,
                    'description' => $item->description(),
                    'quantity' => $item->quantity(),
                    'unit_price' => $item->unitPrice()->amount() / 100,
                    'total' => $item->total()->amount() / 100,
                ],
            );
        }
    }

    private function toDomain(InvoiceModel $model): Invoice
    {
        $currency = $model->currency;

        $invoice = new Invoice(
            id: Uuid::fromString($model->id),
            tenantId: Uuid::fromString($model->tenant_id),
            subscriptionId: Uuid::fromString($model->subscription_id),
            invoiceNumber: InvoiceNumber::fromString($model->invoice_number),
            status: InvoiceStatus::from($model->status),
            currency: $currency,
            subtotal: new Money((int) round((float) $model->subtotal * 100), $currency),
            taxAmount: new Money((int) round((float) $model->tax_amount * 100), $currency),
            discountAmount: new Money((int) round((float) $model->discount_amount * 100), $currency),
            total: new Money((int) round((float) $model->total * 100), $currency),
            dueDate: new DateTimeImmutable((string) $model->due_date),
            paidAt: $model->paid_at ? new DateTimeImmutable((string) $model->paid_at) : null,
            voidedAt: $model->voided_at ? new DateTimeImmutable((string) $model->voided_at) : null,
            createdAt: $model->created_at ? new DateTimeImmutable((string) $model->created_at) : null,
        );

        $items = [];

        foreach ($model->items as $itemModel) {
            $items[] = new InvoiceItem(
                id: Uuid::fromString($itemModel->id),
                invoiceId: Uuid::fromString($itemModel->invoice_id),
                type: InvoiceItemType::from($itemModel->type),
                description: $itemModel->description,
                quantity: (int) $itemModel->quantity,
                unitPrice: new Money((int) round((float) $itemModel->unit_price * 100), $currency),
                total: new Money((int) round((float) $itemModel->total * 100), $currency),
            );
        }

        $invoice->loadItems($items);

        return $invoice;
    }
}
