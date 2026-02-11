<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class InvoiceDTO
{
    /**
     * @param  array<InvoiceItemDTO>  $items
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $subscriptionId,
        public string $invoiceNumber,
        public string $status,
        public string $currency,
        public int $subtotalInCents,
        public int $taxAmountInCents,
        public int $discountAmountInCents,
        public int $totalInCents,
        public string $dueDate,
        public array $items = [],
        public ?string $paidAt = null,
        public ?string $voidedAt = null,
        public ?string $createdAt = null,
    ) {}
}
