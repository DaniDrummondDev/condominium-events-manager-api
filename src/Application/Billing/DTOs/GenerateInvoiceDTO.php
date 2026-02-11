<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class GenerateInvoiceDTO
{
    public function __construct(
        public string $subscriptionId,
        public string $periodStart,
        public string $periodEnd,
    ) {}
}
