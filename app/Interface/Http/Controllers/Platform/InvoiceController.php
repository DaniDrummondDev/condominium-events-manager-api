<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Platform;

use App\Interface\Http\Resources\Platform\InvoiceResource;
use Application\Billing\Contracts\InvoiceRepositoryInterface;
use Application\Billing\DTOs\InvoiceDTO;
use Application\Billing\DTOs\InvoiceItemDTO;
use Domain\Billing\Entities\Invoice;
use Domain\Billing\Entities\InvoiceItem;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController
{
    public function index(Request $request, InvoiceRepositoryInterface $invoiceRepository): JsonResponse
    {
        $tenantId = $request->query('tenant_id');

        if ($tenantId === null) {
            return new JsonResponse([
                'error' => 'TENANT_ID_REQUIRED',
                'message' => 'tenant_id query parameter is required',
            ], 422);
        }

        $invoices = $invoiceRepository->findByTenantId(Uuid::fromString($tenantId));
        $dtos = array_map(fn (Invoice $invoice) => $this->toDTO($invoice), $invoices);

        return InvoiceResource::collection($dtos)->response();
    }

    public function show(string $id, InvoiceRepositoryInterface $invoiceRepository): JsonResponse
    {
        $invoice = $invoiceRepository->findById(Uuid::fromString($id));

        if ($invoice === null) {
            return new JsonResponse([
                'error' => 'INVOICE_NOT_FOUND',
                'message' => 'Invoice not found',
            ], 404);
        }

        return (new InvoiceResource($this->toDTO($invoice)))->response();
    }

    private function toDTO(Invoice $invoice): InvoiceDTO
    {
        return new InvoiceDTO(
            id: $invoice->id()->value(),
            tenantId: $invoice->tenantId()->value(),
            subscriptionId: $invoice->subscriptionId()->value(),
            invoiceNumber: $invoice->invoiceNumber()->value(),
            status: $invoice->status()->value,
            currency: $invoice->currency(),
            subtotalInCents: $invoice->subtotal()->amount(),
            taxAmountInCents: $invoice->taxAmount()->amount(),
            discountAmountInCents: $invoice->discountAmount()->amount(),
            totalInCents: $invoice->total()->amount(),
            dueDate: $invoice->dueDate()->format('Y-m-d'),
            items: array_map(fn (InvoiceItem $item) => new InvoiceItemDTO(
                id: $item->id()->value(),
                invoiceId: $item->invoiceId()->value(),
                type: $item->type()->value,
                description: $item->description(),
                quantity: $item->quantity(),
                unitPriceInCents: $item->unitPrice()->amount(),
                totalInCents: $item->total()->amount(),
            ), $invoice->items()),
            paidAt: $invoice->paidAt()?->format('c'),
            voidedAt: $invoice->voidedAt()?->format('c'),
            createdAt: $invoice->createdAt()?->format('c'),
        );
    }
}
