<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class ChargeRequestDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $invoiceId,
        public int $amountInCents,
        public string $currency,
        public string $paymentMethodToken,
        public array $metadata = [],
    ) {}
}
