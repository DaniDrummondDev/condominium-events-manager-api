<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class InvoiceItemDTO
{
    public function __construct(
        public string $id,
        public string $invoiceId,
        public string $type,
        public string $description,
        public int $quantity,
        public int $unitPriceInCents,
        public int $totalInCents,
    ) {}
}
